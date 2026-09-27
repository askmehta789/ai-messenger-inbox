<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
$business = current_business($db);
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $act = $_POST['act'] ?? '';

    if ($act === 'create_business') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name !== '') {
            $db->prepare('INSERT INTO businesses (name) VALUES (?)')->execute([$name]);
            $_SESSION['business_id'] = (int)$db->lastInsertId();
        }
        header('Location: settings.php'); exit;
    }

    if ($business && $act === 'save_ai') {
        $provider = (string)($_POST['ai_provider'] ?? '');
        $model = trim((string)($_POST['ai_model'] ?? ''));
        $instructions = trim((string)($_POST['instructions'] ?? ''));
        $newKey = trim((string)($_POST['ai_api_key'] ?? ''));

        $sql = 'UPDATE businesses SET ai_provider=?, ai_model=?, instructions=?';
        $args = [$provider, $model, $instructions];
        if ($newKey !== '') {
            $sql .= ', ai_api_key_encrypted=?';
            $args[] = ai_encrypt($newKey, $cfg['crypto_key']);
        }
        $sql .= ' WHERE id=?';
        $args[] = $business['id'];
        $db->prepare($sql)->execute($args);
        $msg = 'AI settings saved.';
        $business = current_business($db);
    }

    if ($business && $act === 'add_page') {
        $pageId = trim((string)($_POST['page_id'] ?? ''));
        $pageName = trim((string)($_POST['page_name'] ?? ''));
        $token = trim((string)($_POST['access_token'] ?? ''));
        if ($pageId !== '' && $token !== '') {
            $db->prepare('INSERT INTO pages (business_id, page_id, page_name, access_token) VALUES (?,?,?,?)
                          ON DUPLICATE KEY UPDATE business_id=VALUES(business_id), page_name=VALUES(page_name), access_token=VALUES(access_token)')
               ->execute([$business['id'], $pageId, $pageName, $token]);
            $msg = 'Page saved.';
        }
    }

    if ($business && $act === 'delete_page') {
        $db->prepare('DELETE FROM pages WHERE id=? AND business_id=?')->execute([(int)$_POST['id'], $business['id']]);
        $msg = 'Page removed.';
    }
}

$pages = $business ? (function () use ($db, $business) {
    $st = $db->prepare('SELECT * FROM pages WHERE business_id=? ORDER BY page_name'); $st->execute([$business['id']]); return $st->fetchAll();
})() : [];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settings — AI Inbox</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f4f5f7}</style></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container py-3" style="max-width:700px">
<?php if ($msg): ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif ?>

<div class="card shadow-sm mb-3"><div class="card-header fw-semibold">Businesses</div><div class="card-body">
 <form method="post" class="d-flex gap-2">
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="create_business">
  <input class="form-control" name="name" placeholder="New business name (e.g. a client's shop)" required>
  <button class="btn btn-dark">Add</button>
 </form>
</div></div>

<?php if (!$business): ?>
 <div class="alert alert-warning">Add a business above to get started.</div>
<?php else: ?>

<div class="card shadow-sm mb-3"><div class="card-header fw-semibold">AI settings — <?= h($business['name']) ?></div><div class="card-body">
 <form method="post">
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="save_ai">
  <div class="mb-2"><label class="form-label small">AI provider</label>
   <select name="ai_provider" class="form-select">
    <option value="">— choose —</option>
    <option value="openai" <?= $business['ai_provider'] === 'openai' ? 'selected' : '' ?>>OpenAI</option>
    <option value="anthropic" <?= $business['ai_provider'] === 'anthropic' ? 'selected' : '' ?>>Anthropic (Claude)</option>
   </select></div>
  <div class="mb-2"><label class="form-label small">Model</label>
   <input class="form-control" name="ai_model" value="<?= h($business['ai_model']) ?>" placeholder="e.g. gpt-4o-mini or claude-3-5-haiku-20241022"></div>
  <div class="mb-2"><label class="form-label small">API key <?= $business['ai_api_key_encrypted'] ? '<span class="text-success">(saved — leave blank to keep it)</span>' : '' ?></label>
   <input type="password" class="form-control" name="ai_api_key" placeholder="Paste API key to set or replace it" autocomplete="off"></div>
  <div class="mb-2"><label class="form-label small">Business context / instructions for the AI</label>
   <textarea class="form-control" name="instructions" rows="4" placeholder="Store name, products, prices, delivery info, tone…"><?= h($business['instructions']) ?></textarea></div>
  <button class="btn btn-primary">Save AI settings</button>
 </form>
</div></div>

<div class="card shadow-sm mb-3"><div class="card-header fw-semibold">Facebook Pages</div><div class="card-body">
 <?php foreach ($pages as $p): ?>
  <div class="d-flex justify-content-between align-items-center border-bottom py-2">
   <div><strong><?= h($p['page_name'] ?: 'Untitled Page') ?></strong><div class="small text-muted"><?= h($p['page_id']) ?></div></div>
   <form method="post" onsubmit="return confirm('Remove this Page?')">
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="delete_page">
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    <button class="btn btn-sm btn-outline-danger">Remove</button>
   </form>
  </div>
 <?php endforeach ?>
 <?php if (!$pages): ?><p class="text-muted small">No Pages added yet.</p><?php endif ?>
 <form method="post" class="row g-2 mt-2">
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="act" value="add_page">
  <div class="col-12 col-sm-4"><input class="form-control form-control-sm" name="page_id" placeholder="Facebook Page ID" required></div>
  <div class="col-12 col-sm-3"><input class="form-control form-control-sm" name="page_name" placeholder="Page name (label)"></div>
  <div class="col-12 col-sm-4"><input class="form-control form-control-sm" name="access_token" placeholder="Page access token" required></div>
  <div class="col-12 col-sm-1"><button class="btn btn-sm btn-dark w-100">Add</button></div>
 </form>
 <p class="small text-muted mt-2 mb-0">Generate the Page access token from your Meta App's Messenger API Settings → Generate access tokens, after subscribing the Page there.</p>
</div></div>

<?php endif ?>
</main>
</body></html>
