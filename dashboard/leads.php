<?php
require_once __DIR__ . '/bootstrap.php';
require_login();
$business = current_business($db);

const STATUSES = ['collecting' => 'Collecting', 'new' => 'New', 'called' => 'Called', 'ordered' => 'Ordered',
                  'not_interested' => 'Not interested', 'invalid' => 'Invalid'];

if ($business && $_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    if (isset(STATUSES[$_POST['status'] ?? ''])) {
        $db->prepare('UPDATE leads SET status=?, notes=? WHERE id=? AND business_id=?')
           ->execute([$_POST['status'], mb_substr((string)$_POST['notes'], 0, 5000), $id, $business['id']]);
    }
    header('Location: ' . ($_POST['back'] ?? './')); exit;
}

$status = $_GET['status'] ?? 'all';
$q = trim((string)($_GET['q'] ?? ''));
$leads = []; $counts = []; $today = 0;
if ($business) {
    $where = ['business_id = ?']; $args = [$business['id']];
    if ($status !== 'all' && isset(STATUSES[$status])) { $where[] = 'status = ?'; $args[] = $status; }
    if ($q !== '') {
        $where[] = '(phone LIKE ? OR name LIKE ? OR address LIKE ? OR product LIKE ?)';
        $like = '%' . $q . '%';
        array_push($args, $like, $like, $like, $like);
    }
    $sql = 'SELECT * FROM leads WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 500';

    if (isset($_GET['export'])) {
        $st = $db->prepare($sql); $st->execute($args);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="leads-' . date('Ymd-Hi') . '.csv"');
        $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Date', 'Name', 'Phone', 'Address', 'Product', 'Status', 'Notes']);
        foreach ($st as $r) fputcsv($out, [$r['created_at'], $r['name'], $r['phone'], $r['address'], $r['product'], STATUSES[$r['status']], $r['notes']]);
        exit;
    }

    $st = $db->prepare($sql); $st->execute($args); $leads = $st->fetchAll();
    $cst = $db->prepare('SELECT status, COUNT(*) c FROM leads WHERE business_id=? GROUP BY status'); $cst->execute([$business['id']]);
    $counts = $cst->fetchAll(PDO::FETCH_KEY_PAIR);
    $tst = $db->prepare('SELECT COUNT(*) FROM leads WHERE business_id=? AND created_at >= CURDATE()'); $tst->execute([$business['id']]); $today = (int)$tst->fetchColumn();
}
$back = $_SERVER['REQUEST_URI'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Leads — AI Inbox</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
 body{background:#f4f5f7}.lead-card{border-left:4px solid #0d6efd}.lead-card.s-called{border-color:#6c757d}
 .lead-card.s-ordered{border-color:#198754}.lead-card.s-collecting{border-color:#adb5bd}
 .lead-card.s-not_interested,.lead-card.s-invalid{border-color:#dc3545}
 .nav-pills .nav-link{padding:.35rem .75rem}
</style></head><body>
<?php include __DIR__ . '/_nav.php'; ?>
<main class="container py-3" style="max-width:900px">
<?php if (!$business): ?>
 <div class="alert alert-warning">No business set up yet. Go to <a href="settings.php">Settings</a> first.</div>
<?php else: ?>
<div class="d-flex justify-content-between align-items-center mb-2">
 <span class="fw-semibold">Leads <span class="badge text-bg-primary"><?= $today ?> today</span></span>
 <a class="btn btn-sm btn-outline-secondary" href="?<?= h(http_build_query(['status' => $status, 'q' => $q, 'export' => 1])) ?>">CSV</a>
</div>
<form class="d-flex gap-2 mb-2"><input type="hidden" name="status" value="<?= h($status) ?>">
 <input class="form-control" name="q" value="<?= h($q) ?>" placeholder="Search name, phone, address or product…"><button class="btn btn-dark">Search</button></form>
<ul class="nav nav-pills flex-nowrap overflow-auto mb-3 small">
<?php foreach (['all' => 'All'] + STATUSES as $k => $label): $n = $k === 'all' ? array_sum($counts) : ($counts[$k] ?? 0); ?>
 <li class="nav-item"><a class="nav-link text-nowrap <?= $status === $k ? 'active' : '' ?>" href="?status=<?= $k ?>"><?= $label ?> <span class="badge text-bg-light"><?= $n ?></span></a></li>
<?php endforeach ?></ul>

<?php if (!$leads): ?><p class="text-center text-muted py-5">No leads here yet.</p><?php endif ?>
<?php foreach ($leads as $l): ?>
<div class="card lead-card s-<?= h($l['status']) ?> mb-2 shadow-sm"><div class="card-body py-2">
 <div class="d-flex justify-content-between flex-wrap gap-2">
  <div><div class="fw-semibold"><?= h($l['name'] ?: 'Messenger customer') ?></div>
   <?php if ($l['phone']): ?><a class="text-decoration-none fw-semibold" href="tel:<?= h($l['phone']) ?>"><?= h($l['phone']) ?></a><?php endif ?>
   <?php if ($l['address']): ?><div class="small text-muted"><?= h($l['address']) ?></div><?php endif ?>
   <?php if ($l['product']): ?><div class="small">Product: <?= h($l['product']) ?></div><?php endif ?></div>
  <div class="text-end small text-muted"><?= ago($l['created_at']) ?><br><?= STATUSES[$l['status']] ?></div>
 </div>
 <form method="post" class="row g-2 align-items-start mt-1">
  <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
  <input type="hidden" name="back" value="<?= h($back) ?>">
  <div class="col-12 col-sm-4"><select name="status" class="form-select form-select-sm">
   <?php foreach (STATUSES as $k => $label): ?><option value="<?= $k ?>" <?= $l['status'] === $k ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></div>
  <div class="col-12 col-sm-6"><textarea name="notes" rows="1" class="form-control form-control-sm" placeholder="Notes"><?= h($l['notes']) ?></textarea></div>
  <div class="col-12 col-sm-2"><button class="btn btn-sm btn-dark w-100">Save</button></div>
 </form>
</div></div>
<?php endforeach ?>
<?php endif ?>
</main>
</body></html>
