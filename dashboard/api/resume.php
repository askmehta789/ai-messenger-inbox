<?php
/** Resume AI — clears the manual-takeover pause for a conversation. */
require_once __DIR__ . '/../bootstrap.php';
require_login();
header('Content-Type: application/json');
check_csrf();

$business = current_business($db);
$pageId = (string)($_POST['page_id'] ?? '');
$psid = (string)($_POST['psid'] ?? '');
if (!$business || $pageId === '' || $psid === '') { http_response_code(400); echo json_encode(['ok' => false]); exit; }

$db->prepare('UPDATE conversations SET human_until = NULL WHERE business_id = ? AND page_id = ? AND psid = ?')
   ->execute([$business['id'], $pageId, $psid]);

echo json_encode(['ok' => true]);
