<?php
$db = new PDO('sqlite:' . __DIR__ . '/hospital_portal.db');
$res = $db->query("PRAGMA table_info(agency_purchases)")->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $r) {
    echo $r['name'] . " - " . $r['type'] . "\n";
}
