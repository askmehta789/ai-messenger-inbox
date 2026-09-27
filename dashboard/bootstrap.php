<?php
require_once __DIR__ . '/../lib/handler.php';
$cfg = ai_config();
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
$db = ai_db($cfg);

function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Bad token'); } }

function require_login(): void
{
    if (empty($_SESSION['user'])) { header('Location: index.php'); exit; }
}

/** All businesses, for the switcher. */
function all_businesses(PDO $db): array
{
    return $db->query('SELECT id, name FROM businesses ORDER BY name')->fetchAll();
}

/** The currently selected business (via ?business_id=, remembered in session), or null if none exist yet. */
function current_business(PDO $db): ?array
{
    $businesses = all_businesses($db);
    if (!$businesses) return null;

    if (isset($_GET['business_id'])) $_SESSION['business_id'] = (int)$_GET['business_id'];
    $id = $_SESSION['business_id'] ?? $businesses[0]['id'];
    if (!in_array($id, array_column($businesses, 'id'))) $id = $businesses[0]['id'];
    $_SESSION['business_id'] = $id;

    $st = $db->prepare('SELECT * FROM businesses WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function ago(string $dt): string
{
    $s = time() - strtotime($dt);
    if ($s < 60) return 'just now';
    if ($s < 3600) return floor($s / 60) . ' min ago';
    if ($s < 86400) return floor($s / 3600) . ' h ago';
    return date('d M, g:i A', strtotime($dt));
}
