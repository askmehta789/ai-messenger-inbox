<?php
/** Webhook payload -> conversation state -> AI reply -> lead save. */

require_once __DIR__ . '/graph.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/ai/ai.php';

function ai_config(): array
{
    $cfg = require __DIR__ . '/../config.php';
    date_default_timezone_set($cfg['timezone'] ?? 'Asia/Kathmandu');
    return $cfg;
}

function ai_db(array $cfg): PDO
{
    $pdo = new PDO($cfg['db']['dsn'], $cfg['db']['user'], $cfg['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '+05:45'");
    return $pdo;
}

/** Look up a Page's business + AI settings + access token in one row. */
function ai_find_page(PDO $db, string $pageId): ?array
{
    $st = $db->prepare("SELECT p.page_id, p.business_id, p.access_token,
                                b.ai_provider, b.ai_api_key_encrypted, b.ai_model, b.instructions
                         FROM pages p JOIN businesses b ON b.id = p.business_id
                         WHERE p.page_id = ?");
    $st->execute([$pageId]);
    return $st->fetch() ?: null;
}

function ai_handle_payload(array $payload, PDO $db, array $cfg): void
{
    if (($payload['object'] ?? '') !== 'page') return;
    foreach ($payload['entry'] ?? [] as $entry) {
        $pageId = (string)($entry['id'] ?? '');
        $page = ai_find_page($db, $pageId);
        if (!$page) { ai_log($cfg, "Unknown page $pageId — add it via Settings first"); continue; }
        foreach ($entry['messaging'] ?? [] as $ev) {
            try {
                ai_handle_event($ev, $page, $db, $cfg, time());
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                ai_log($cfg, 'Event error: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            }
        }
    }
}

function ai_handle_event(array $ev, array $page, PDO $db, array $cfg, int $now): void
{
    $businessId = (int)$page['business_id'];
    $pageId = $page['page_id'];
    $token = $page['access_token'];
    $m = $ev['message'] ?? null;

    // ---------- echoes: our own sends, or a human agent replying from the real Facebook Inbox ----------
    if ($m && !empty($m['is_echo'])) {
        $psid = (string)($ev['recipient']['id'] ?? '');
        $ours = ($m['metadata'] ?? '') === 'ai_bot' || (string)($m['app_id'] ?? '') === (string)$cfg['app_id'];
        if ($ours || $psid === '') return;
        ai_log_msg($db, $businessId, $pageId, $psid, 'echo', $m['mid'] ?? null, $m['text'] ?? '[attachment]');
        $pause = $now + 12 * 3600;
        $db->prepare("INSERT INTO conversations (business_id, page_id, psid, human_until) VALUES (?,?,?,?)
                      ON DUPLICATE KEY UPDATE human_until = GREATEST(COALESCE(human_until,0), VALUES(human_until))")
           ->execute([$businessId, $pageId, $psid, $pause]);
        return;
    }

    $psid = (string)($ev['sender']['id'] ?? '');
    if ($psid === '' || $psid === $pageId) return;

    // ---------- only plain text drives the AI for now; log anything else and stop ----------
    $text = (string)($m['text'] ?? '');
    $mid = $m['mid'] ?? null;
    if ($text === '') {
        if ($m) ai_log_msg($db, $businessId, $pageId, $psid, 'in', $mid, '[attachment]');
        return;
    }

    if (!ai_log_msg($db, $businessId, $pageId, $psid, 'in', $mid, $text)) return; // Meta retries webhooks: dedupe by mid

    $db->prepare("INSERT IGNORE INTO conversations (business_id, page_id, psid) VALUES (?,?,?)")->execute([$businessId, $pageId, $psid]);

    $db->beginTransaction();
    $st = $db->prepare("SELECT * FROM conversations WHERE page_id=? AND psid=? FOR UPDATE");
    $st->execute([$pageId, $psid]);
    $conv = $st->fetch();

    if (!$conv['customer_name'] && ($name = ai_user_name($cfg, $token, $psid))) {
        $db->prepare("UPDATE conversations SET customer_name=? WHERE id=?")->execute([$name, $conv['id']]);
    }

    ai_update($db, 'conversations', ['last_message' => mb_substr($text, 0, 1000), 'last_seen' => date('Y-m-d H:i:s', $now)], (int)$conv['id']);

    $inHumanWindow = $conv['human_until'] && (int)$conv['human_until'] > $now;
    $db->commit();
    if ($inHumanWindow) return; // an agent is handling this conversation manually — AI stays silent

    // ---------- known lead fields so the AI doesn't re-ask ----------
    $leadSt = $db->prepare("SELECT name, phone, address, product FROM leads WHERE page_id=? AND psid=?");
    $leadSt->execute([$pageId, $psid]);
    $lead = $leadSt->fetch() ?: ['name' => null, 'phone' => null, 'address' => null, 'product' => null];

    // ---------- recent history for context (excluding the message we just logged) ----------
    $histSt = $db->prepare("SELECT direction, body FROM messages WHERE page_id=? AND psid=? AND direction IN ('in','out')
                             ORDER BY id DESC LIMIT 16");
    $histSt->execute([$pageId, $psid]);
    $rows = array_reverse($histSt->fetchAll());
    $history = [];
    foreach ($rows as $r) {
        if ($r['body'] === null) continue;
        $history[] = ['role' => $r['direction'] === 'out' ? 'assistant' : 'user', 'text' => $r['body']];
    }
    if ($history && end($history)['role'] === 'user' && end($history)['text'] === $text) array_pop($history);

    $business = [
        'ai_provider'  => $page['ai_provider'],
        'ai_api_key'   => ai_decrypt($page['ai_api_key_encrypted'], $cfg['crypto_key']),
        'ai_model'     => $page['ai_model'],
        'instructions' => $page['instructions'],
    ];
    if (!$business['ai_provider'] || !$business['ai_api_key']) {
        ai_log($cfg, "Business $businessId has no AI configured — skipping reply");
        return;
    }

    $result = ai_generate_reply($cfg, $business, $history, $text, $lead);

    if (!empty($result['reply'])) {
        $r = ai_send_text($cfg, $token, $psid, $result['reply']);
        ai_log_msg($db, $businessId, $pageId, $psid, 'out', $r['data']['message_id'] ?? null, $result['reply']);
    }

    ai_merge_lead($db, $businessId, $pageId, $psid, $lead, $result);
}

/** Merge newly-extracted fields into the lead row; never overwrite a known field with null. */
function ai_merge_lead(PDO $db, int $businessId, string $pageId, string $psid, array $known, array $result): void
{
    $fields = [];
    foreach (['name', 'phone', 'address', 'product'] as $f) {
        if (!empty($result[$f])) $fields[$f] = $result[$f];
    }
    if (!$fields) return;

    $merged = array_merge($known, $fields);
    $complete = $merged['name'] && $merged['phone'] && $merged['address'] && $merged['product'];
    $fields['status'] = $complete ? 'new' : 'collecting';

    $cols = implode(', ', array_map(fn($k) => "`$k`=?", array_keys($fields)));
    $db->prepare("INSERT INTO leads (business_id, page_id, psid, name, phone, address, product, status)
                  VALUES (?,?,?,?,?,?,?,?)
                  ON DUPLICATE KEY UPDATE $cols")
       ->execute([
           $businessId, $pageId, $psid,
           $merged['name'], $merged['phone'], $merged['address'], $merged['product'], $fields['status'],
           ...array_values($fields),
       ]);
}

/** Returns false if this message id was already processed (Meta retries webhooks). */
function ai_log_msg(PDO $db, int $businessId, string $pageId, string $psid, string $dir, ?string $mid, string $body): bool
{
    $st = $db->prepare("INSERT IGNORE INTO messages (business_id, page_id, psid, direction, mid, body) VALUES (?,?,?,?,?,?)");
    $st->execute([$businessId, $pageId, $psid, $dir, $mid, mb_substr($body, 0, 2000)]);
    return $mid === null || $st->rowCount() > 0;
}

function ai_update(PDO $db, string $table, array $set, int $id): void
{
    if (!$set) return;
    $cols = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($set)));
    $db->prepare("UPDATE `$table` SET $cols WHERE id = ?")->execute([...array_values($set), $id]);
}
