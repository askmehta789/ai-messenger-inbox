<?php
require_once __DIR__ . '/../bootstrap.php';
require_login();
header('Content-Type: application/json');

$business = current_business($db);
if (!$business) { echo json_encode([]); exit; }

$st = $db->prepare("SELECT c.page_id, c.psid, c.customer_name, c.last_message, c.last_seen, c.human_until,
                            p.page_name, l.status AS lead_status
                     FROM conversations c
                     JOIN pages p ON p.page_id = c.page_id
                     LEFT JOIN leads l ON l.page_id = c.page_id AND l.psid = c.psid
                     WHERE c.business_id = ?
                     ORDER BY c.last_seen DESC LIMIT 100");
$st->execute([$business['id']]);
$now = time();
$rows = array_map(function ($r) use ($now) {
    $r['is_human'] = $r['human_until'] && (int)$r['human_until'] > $now;
    $r['ago'] = ago($r['last_seen']);
    return $r;
}, $st->fetchAll());

echo json_encode($rows);
