<?php
/** Manual agent reply — sends via Graph API and pauses the AI for this conversation. */
require_once __DIR__ . '/../bootstrap.php';
require_login();
header('Content-Type: application/json');
check_csrf();

$business = current_business($db);
$pageId = (string)($_POST['page_id'] ?? '');
$psid = (string)($_POST['psid'] ?? '');
$text = trim((string)($_POST['text'] ?? ''));
if (!$business || $pageId === '' || $psid === '' || $text === '') { http_response_code(400); echo json_encode(['ok' => false]); exit; }

$st = $db->prepare('SELECT access_token FROM pages WHERE page_id = ? AND business_id = ?');
$st->execute([$pageId, $business['id']]);
$token = $st->fetchColumn();
if (!$token) { http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Page not found']); exit; }

$r = ai_send_text($cfg, $token, $psid, $text);
ai_log_msg($db, (int)$business['id'], $pageId, $psid, 'agent', $r['data']['message_id'] ?? null, $text);

$pause = time() + 12 * 3600;
$db->prepare("INSERT INTO conversations (business_id, page_id, psid, human_until) VALUES (?,?,?,?)
              ON DUPLICATE KEY UPDATE human_until = ?")
   ->execute([$business['id'], $pageId, $psid, $pause, $pause]);

echo json_encode(['ok' => $r['ok']]);
