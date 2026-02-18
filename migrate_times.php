<?php
require __DIR__ . '/config/db.php';

echo "Starting migration...\n";

try {
    // 1. Add Columns if not exist (Separate try-catch for safety if no IF NOT EXISTS support)
    try { $pdo->exec("ALTER TABLE timetable ADD COLUMN start_time TIME NULL"); } catch (Exception $e) {}
    try { $pdo->exec("ALTER TABLE timetable ADD COLUMN end_time TIME NULL"); } catch (Exception $e) {}
    
    echo "Columns added (or already existed).\n";

    // 2. Fetch all rows with time_slot
    $stmt = $pdo->query("SELECT id, time_slot FROM timetable");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    $updateStmt = $pdo->prepare("UPDATE timetable SET start_time = ?, end_time = ? WHERE id = ?");

    foreach ($rows as $row) {
        $slot = $row['time_slot'];
        // Expected format: "HH:MM - HH:MM"
        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $slot, $matches)) {
            $start = date("H:i:s", strtotime($matches[1]));
            $end = date("H:i:s", strtotime($matches[2]));
            
            $updateStmt->execute([$start, $end, $row['id']]);
            $count++;
        }
    }

    echo "Updated $count records with parsed times.\n";
    echo "Migration completed successfully.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
