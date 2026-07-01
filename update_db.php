<?php
require 'config.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE room_bookings ADD COLUMN activity_category VARCHAR(100) AFTER room_id");
    echo "Added activity_category\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE room_bookings ADD COLUMN pic_name VARCHAR(150) AFTER activity_category");
    echo "Added pic_name\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }

try {
    $db->exec("ALTER TABLE room_bookings ADD COLUMN pic_id VARCHAR(50) AFTER pic_name");
    echo "Added pic_id\n";
} catch(Exception $e) { echo $e->getMessage() . "\n"; }
