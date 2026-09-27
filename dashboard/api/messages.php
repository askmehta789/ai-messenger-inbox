<?php
/** Message thread for one conversation, scoped to the current business. */
require_once __DIR__ . '/../bootstrap.php';
require_login();
header('Content-Type: application/json');

$business = current_business($db);
$pageId = (string)($_GET['page_id'] ?? '');
$psid = (string)($_GET['psid'] ?? '');
if (!$business || $pageId === '' || $psid === '') { echo json_encode([]); exit; }

$st = $db->prepare("SELECT direction, body, created_at FROM messages
                     WHERE business_id = ? AND page_id = ? AND psid = ?
                     ORDER BY id ASC LIMIT 200");
$st->execute([$business['id'], $pageId, $psid]);
echo json_encode($st->fetchAll());
