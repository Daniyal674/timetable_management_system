<?php
// src/Controllers/TimetableController.php

namespace App\Controllers;

use App\Services\PdfParserService;

class TimetableController
{
    private $pdo;
    private $pdfParser;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
        $this->pdfParser = new PdfParserService();
    }

    public function index()
    {
        $data = $this->getTimetableData();
        extract($data);
        require '../src/Views/admin/timetable.php';
    }

    public function publicIndex()
    {
        $data = $this->getTimetableData();
        extract($data);
        require '../src/Views/public/timetable.php';
    }

    private function getTimetableData()
    {
        // 1. Fetch Filter Data
        $semStmt = $this->pdo->query("SELECT s.id, s.number, b.name as batch_name 
                                      FROM semesters s 
                                      JOIN batches b ON s.batch_id = b.id 
                                      ORDER BY b.name, s.number");
        $semesters = $semStmt->fetchAll();

        $batchesStmt = $this->pdo->query("SELECT * FROM batches ORDER BY name");
        $batches = $batchesStmt->fetchAll();

        // Fetch Sections if Batch selected, else all (or empty?)
        // For UI simplicity, we might fetch all and let JS filter, or just fetch all.
        $sectionsStmt = $this->pdo->query("SELECT s.*, b.name as batch_name FROM sections s JOIN batches b ON s.batch_id = b.id ORDER BY b.name, s.name");
        $sections = $sectionsStmt->fetchAll();

        // 2. Build Query with Filters
        $where = ["1=1"];
        $params = [];

        if (!empty($_GET['semester_id'])) {
            $where[] = "t.semester_id = ?";
            $params[] = $_GET['semester_id'];
        }

        if (!empty($_GET['batch_id'])) {
            $where[] = "t.batch_id = ?";
            $params[] = $_GET['batch_id'];
        }

        if (!empty($_GET['section_id'])) {
            $where[] = "t.section_id = ?";
            $params[] = $_GET['section_id'];
        }

        if (!empty($_GET['subject_search'])) {
            $where[] = "s.name LIKE ?";
            $params[] = '%' . $_GET['subject_search'] . '%';
        }

        if (!empty($_GET['teacher_search'])) {
            $where[] = "tr.name LIKE ?";
            $params[] = '%' . $_GET['teacher_search'] . '%';
        }

        $whereClause = implode(" AND ", $where);

        $sql = "SELECT t.*, s.name as subject_name, s.code as subject_code, 
                       tr.name as teacher_name, r.name as room_name, 
                       sec.name as section_name, sec.type as section_type, b.name as batch_name, sem.number as semester_num 
                FROM timetable t
                LEFT JOIN subjects s ON t.subject_id = s.id
                LEFT JOIN teachers tr ON t.teacher_id = tr.id
                LEFT JOIN rooms r ON t.room_id = r.id
                LEFT JOIN sections sec ON t.section_id = sec.id
                LEFT JOIN batches b ON t.batch_id = b.id
                LEFT JOIN semesters sem ON t.semester_id = sem.id
                WHERE $whereClause
                ORDER BY t.day, t.time_slot";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $timetables = $stmt->fetchAll();

        // 3. Organize data
        $roomStmt = $this->pdo->query("SELECT * FROM rooms ORDER BY name");
        $allRooms = $roomStmt->fetchAll();

        $matrix = [];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        foreach ($timetables as $row) {
            $d = $row['day'];
            $rid = $row['room_id'] ?? 0;
            $matrix[$d][$rid][] = $row;
        }

        return compact('semesters', 'batches', 'sections', 'timetables', 'allRooms', 'matrix', 'days');
    }

    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Handle manual creation
            $batchId = $_POST['batch_id'] ?? null;
            $sectionId = $_POST['section_id'] ?? null;
            $subjectId = $_POST['subject_id'] ?? null;
            $teacherId = $_POST['teacher_id'] ?? null;
            $roomId = $_POST['room_id'] ?? null;
            $day = $_POST['day'] ?? null;
            $timeSlot = $_POST['time_slot'] ?? null;

            if ($batchId && $sectionId && $subjectId && $day && $timeSlot) {
                // Fetch semester for batch (MVP: assume sem 1 or user selects)
                // For now, let's just insert.
                // Ideally user selects semester too.
                $semesterId = $_POST['semester_id'] ?? 1; // Default
                
                $times = $this->parseTimeSlot($timeSlot);

                $stmt = $this->pdo->prepare("INSERT INTO timetable 
                    (batch_id, section_id, semester_id, subject_id, teacher_id, room_id, day, time_slot, start_time, end_time, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')");
                $stmt->execute([$batchId, $sectionId, $semesterId, $subjectId, $teacherId, $roomId, $day, $timeSlot, $times[0], $times[1]]);
                $_SESSION['flash_message'] = "Class added successfully.";
            } else {
                $_SESSION['flash_message'] = "Error: Missing required fields.";
            }
            header('Location: /admin/timetable');
            exit;
        }
    }

    public function upload()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['timetable_pdf'])) {
            $file = $_FILES['timetable_pdf'];
            $uploadDir = __DIR__ . '/../../public/uploads/';
            if (!is_dir($uploadDir))
                mkdir($uploadDir, 0777, true);

            $filePath = $uploadDir . basename($file['name']);
            move_uploaded_file($file['tmp_name'], $filePath);

            try {
                // Call Python Parser via Service
                $parsedData = $this->pdfParser->parseTimetable($filePath);

                if (isset($parsedData['error'])) {
                    $_SESSION['flash_message'] = "Error parsing PDF: " . $parsedData['error'];
                } else {
                    $_SESSION['parsed_data'] = $parsedData;
                    $_SESSION['flash_message'] = "PDF parsed successfully! Please review data.";
                }

            } catch (\Exception $e) {
                $_SESSION['flash_message'] = "Error: " . $e->getMessage();
            }

            header('Location: /admin/timetable');
            exit;
        }
        header('Location: /admin/timetable');
        exit;
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['parsed_data'])) {
            $data = $_SESSION['parsed_data'];
            $count = 0;

            try {
                $this->pdo->beginTransaction();

                foreach ($data as $row) {
                    if (empty($row['day']) || empty($row['time_slot']))
                        continue;

                    // Truncate strings to schema limits
                    $subjectName = substr($row['subject'] ?? 'Unknown Subject', 0, 100);
                    $teacherName = substr($row['teacher'] ?? 'TBA', 0, 100);
                    $roomName = substr($row['room'] ?? 'TBA', 0, 50);
                    $batchName = substr($row['batch'] ?? 'General', 0, 50);
                    $sectionName = substr($row['section'] ?? 'A', 0, 50);
                    $subjectCode = substr($row['subject_code'] ?? null, 0, 20);

                    // Semester and Batch are coupled
                    $semesterId = $this->getOrCreateSemester($row['semester'] ?? '1', $batchName);

                    $subjectId = $this->getOrCreate('subjects', 'name', $subjectName, [
                        'code' => $subjectCode,
                        'semester_id' => $semesterId
                    ]);

                    $teacherId = $this->getOrCreate('teachers', 'name', $teacherName);
                    // Room might be empty or TBA
                    $roomId = null;
                    if (!empty($roomName) && $roomName !== 'TBA') {
                        $roomId = $this->getOrCreate('rooms', 'name', $roomName);
                    }

                    $batchId = $this->getOrCreate('batches', 'name', $batchName);
                    $sectionId = $this->getOrCreate('sections', 'name', $sectionName, ['batch_id' => $batchId]);

                    // Check for existing entry for this Section + Day + Time
                    $checkStmt = $this->pdo->prepare("SELECT id FROM timetable 
                                                      WHERE batch_id = ? AND section_id = ? AND day = ? AND time_slot = ?");
                    $checkStmt->execute([$batchId, $sectionId, $row['day'], $row['time_slot']]);
                    $existingId = $checkStmt->fetchColumn();

                    if ($existingId) {
                        // Update existing
                        $stmt = $this->pdo->prepare("UPDATE timetable 
                            SET semester_id=?, subject_id=?, teacher_id=?, room_id=?, status='scheduled'
                            WHERE id=?");
                        $stmt->execute([
                            $semesterId,
                            $subjectId,
                            $teacherId,
                            $roomId,
                            $existingId
                        ]);
                    } else {
                        // Insert new
                        $stmt = $this->pdo->prepare("INSERT INTO timetable 
                            (batch_id, section_id, semester_id, subject_id, teacher_id, room_id, day, time_slot, start_time, end_time, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')");

                        // Parse time
                        $times = $this->parseTimeSlot($row['time_slot']);

                        $stmt->execute([
                            $batchId,
                            $sectionId,
                            $semesterId,
                            $subjectId,
                            $teacherId,
                            $roomId,
                            $row['day'],
                            $row['time_slot'],
                            $times[0],
                            $times[1]
                        ]);
                    }
                    $count++;
                }

                $this->pdo->commit();
                unset($_SESSION['parsed_data']);
                $_SESSION['flash_message'] = "Successfully processed $count classes (Merged/Saved)!";

            } catch (\Exception $e) {
                $this->pdo->rollBack();
                // Log error for debugging
                error_log($e->getMessage());
                $_SESSION['flash_message'] = "Error saving data: " . $e->getMessage();
            }

            header('Location: /admin/timetable');
            exit;
        }
    }

    public function clear()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
                exit;

            try {
                $this->pdo->exec("DELETE FROM timetable");
                $this->pdo->exec("ALTER TABLE timetable AUTO_INCREMENT = 1");
                $_SESSION['flash_message'] = "Timetable cleared successfully.";
            } catch (\Exception $e) {
                $_SESSION['flash_message'] = "Error clearing data: " . $e->getMessage();
            }
            header('Location: /admin/timetable');
            exit;
        }
    }

    private function getOrCreate($table, $col, $value, $extra = [])
    {
        if (empty($value))
            return null;
        $stmt = $this->pdo->prepare("SELECT id FROM $table WHERE $col = ?");
        $stmt->execute([$value]);
        $id = $stmt->fetchColumn();
        if ($id)
            return $id;

        $cols = [$col];
        $vals = [$value];
        foreach ($extra as $k => $v) {
            if ($v !== null) {
                $cols[] = $k;
                $vals[] = $v;
            }
        }
        $sql = "INSERT INTO $table (" . implode(',', $cols) . ") VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($vals);
        return $this->pdo->lastInsertId();
    }

    private function getOrCreateSemester($number, $batchName)
    {
        $batchId = $this->getOrCreate('batches', 'name', $batchName);

        $cleanNumber = 0;
        if (is_numeric($number)) {
            $cleanNumber = (int) $number;
        } else {
            if (preg_match('/(\d+)/', $number, $matches)) {
                $cleanNumber = (int) $matches[1];
            }
        }
        if ($cleanNumber < 0)
            $cleanNumber = 0;

        $stmt = $this->pdo->prepare("SELECT id FROM semesters WHERE batch_id = ? AND number = ?");
        $stmt->execute([$batchId, $cleanNumber]);
        $id = $stmt->fetchColumn();

        if ($id)
            return $id;

        $stmt = $this->pdo->prepare("INSERT INTO semesters (batch_id, number) VALUES (?, ?)");
        $stmt->execute([$batchId, $cleanNumber]);
        return $this->pdo->lastInsertId();
    }

    private function parseTimeSlot($slot)
    {
        // Expected format: "08:00 - 09:30"
        if (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $slot, $matches)) {
            $start = date("H:i:s", strtotime($matches[1]));
            $end = date("H:i:s", strtotime($matches[2]));
            return [$start, $end];
        }
        return [null, null];
    }
}
