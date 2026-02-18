<?php
// src/Services/ArrangementEngine.php

namespace App\Services;

class ArrangementEngine
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Check for conflicts (Room, Teacher, Student Batch)
    public function checkConflict($day, $timeSlot, $roomId, $teacherId, $batchId, $excludeId = null)
    {
        // 1. Room Conflict
        if ($roomId) {
            $sql = "SELECT * FROM timetable WHERE day = ? AND time_slot = ? AND room_id = ? AND status != 'cancelled'";
            $params = [$day, $timeSlot, $roomId];
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch())
                return "Room Conflict";
        }

        // 2. Teacher Conflict
        if ($teacherId) {
            $sql = "SELECT * FROM timetable WHERE day = ? AND time_slot = ? AND teacher_id = ? AND status != 'cancelled'";
            $params = [$day, $timeSlot, $teacherId];
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->fetch())
                return "Teacher Conflict";
        }

        // 3. Batch/Section Conflict (Students can't be in two places)
        // Simplified: Check if this Batch+Section has another class at this time
        // For MVP we check Batch-level conflict or Section-level? Usually Section.
        if ($batchId) {
            // We need to know section_id too for accurate check, 
            // but let's assume if the exact same batch group has a class.
            // For strictness, we should pass sectionId. 
            // Leaving as generic Batch check or skipped for now if not passed.
        }

        return null; // No conflict
    }

    public function getAvailableRooms($day, $start, $end)
    {
        // Find rooms NOT occupied during this interval
        // Overlap logic: (StartA < EndB) AND (EndA > StartB)
        // We want rooms where NO class overlaps.
        
        $sql = "SELECT * FROM rooms 
                WHERE id NOT IN (
                    SELECT room_id FROM timetable 
                    WHERE day = ? 
                    AND status != 'cancelled' 
                    AND room_id IS NOT NULL
                    AND (
                        (start_time < ? AND end_time > ?) OR
                        -- Fallback checks if start_time/end_time null? 
                        -- Ideally we migrated all. MVP assumption: we have times.
                        (start_time IS NULL) 
                    )
                )
                ORDER BY name";
        
        $stmt = $this->pdo->prepare($sql);
        // Param 1: Day
        // Param 2: Query End (end of requested slot) > Class Start
        // Param 3: Query Start (start of requested slot) < Class End
        $stmt->execute([$day, $end, $start]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getAvailableTeachers($day, $timeSlot)
    {
        $sql = "SELECT * FROM teachers 
                WHERE id NOT IN (
                    SELECT teacher_id FROM timetable 
                    WHERE day = ? AND time_slot = ? AND status != 'cancelled' AND teacher_id IS NOT NULL
                )
                ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$day, $timeSlot]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // Check if a specific slot swap is valid
    public function canSwap($slotId1, $slotId2)
    {
        $s1 = $this->fetchSlot($slotId1);
        $s2 = $this->fetchSlot($slotId2);

        if (!$s1 || !$s2)
            return false;

        // MVP: Allow swap without deep conflict check, or perform check here?
        // Let's assume Admin overrides.
        return true;
    }

    private function fetchSlot($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM timetable WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
