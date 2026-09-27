<?php
/** Dashboard login. */
require_once __DIR__ . '/bootstrap.php';

if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; }
if (!empty($_SESSION['user'])) { header('Location: inbox.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim((string)($_POST['u'] ?? ''));
    $p = (string)($_POST['p'] ?? '');
    $st = $db->prepare('SELECT password_hash FROM admin_users WHERE username = ?');
    $st->execute([$u]);
    $hash = $st->fetchColumn();
    if ($hash && password_verify($p, $hash)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $u;
        header('Location: inbox.php'); exit;
    }
    $err = 'Wrong username or password';
    usleep(400000);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>AI Inbox — Login</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh"><form method="post" class="card p-4 mx-auto shadow-sm" style="max-width:340px;width:100%">
<h5 class="mb-3">AI Messenger Inbox</h5><?php if ($err): ?><div class="alert alert-danger py-2"><?= h($err) ?></div><?php endif ?>
<input name="u" class="form-control mb-2" placeholder="Username" autocomplete="username" required>
<input name="p" type="password" class="form-control mb-3" placeholder="Password" autocomplete="current-password" required>
<button class="btn btn-primary w-100">Log in</button></form></body></html>
