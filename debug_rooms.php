<?php
require_once 'config.php';
$db = getDB();
$rooms = $db->query("SELECT * FROM rooms WHERE is_active = 1 ORDER BY id")->fetchAll();
echo "Active rooms: " . count($rooms) . "\n";
foreach ($rooms as $r) {
    echo "  - ID:{$r['id']} | {$r['name']} | capacity:{$r['capacity']}\n";
}
$all = $db->query("SELECT * FROM rooms ORDER BY id")->fetchAll();
echo "Total rooms: " . count($all) . "\n";
