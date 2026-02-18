<?php
require 'config/db.php';
$stmt = $pdo->query("SELECT DISTINCT time_slot FROM timetable ORDER BY time_slot");
$slots = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Distinct Time Slots in DB:\n";
foreach ($slots as $slot) {
    echo "'$slot'\n";
}
