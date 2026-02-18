<?php
// public/index.php

session_start();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/db.php';

use App\Controllers\AuthController;
use App\Controllers\TimetableController;
use App\Controllers\RoomController;
use App\Controllers\TeacherController;
use App\Controllers\AcademicController;
use App\Controllers\ArrangementController;
use App\Controllers\ExportController;

// Simple Router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Dependency Injection
$authController = new AuthController($pdo);
$timetableController = new TimetableController($pdo);
$roomController = new RoomController($pdo);
$teacherController = new TeacherController($pdo);
$academicController = new AcademicController($pdo);
$arrangementController = new ArrangementController($pdo);
$exportController = new ExportController($pdo);

// Routing Logic
switch ($uri) {
    case '/':
        $timetableController->publicIndex();
        break;
    case '/login':
        $authController->login();
        break;
    case '/logout':
        $authController->logout();
        break;

    // Admin Routes
    case '/admin/dashboard':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        require '../src/Views/admin/dashboard.php';
        break;

    case '/admin/timetable':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $timetableController->index();
        break;

    case '/admin/timetable/upload':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $timetableController->upload();
        break;

    case '/admin/timetable/save':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $timetableController->save();
        break;

    case '/admin/timetable/clear':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $timetableController->clear();
        break;

    // Room Routes
    case '/admin/rooms':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $roomController->index();
        break;
    case '/admin/rooms/create':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $roomController->create();
        break;

    // Teacher Routes
    case '/admin/teachers':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $teacherController->index();
        break;
    case '/admin/teachers/create':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $teacherController->create();
        break;

    // Academic Routes (Batches, Sections, Semesters, Subjects)
    case '/admin/academic':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $academicController->index();
        break;
    case '/admin/academic/create-batch':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $academicController->createBatch();
        break;
    case '/admin/academic/create-section':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $academicController->createSection();
        break;
    case '/admin/academic/create-semester':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $academicController->createSemester();
        break;
    case '/admin/academic/create-subject':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $academicController->createSubject();
        break;

    // Arrangement Routes
    case (preg_match('/^\/admin\/arrangement\/edit\/(\d+)$/', $uri, $matches) ? true : false):
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
            header('Location: /login');
            exit;
        }
        $arrangementController->editSlot($matches[1]);
        break;
    case '/admin/arrangement/update':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $arrangementController->updateSlot();
        break;
    case '/api/arrangement/swap':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $arrangementController->swapSlots();
        break;
    case '/api/arrangement/available-rooms':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $arrangementController->apiAvailableRooms();
        break;
    case '/api/arrangement/status':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $arrangementController->setStatus();
        break;

    // Export Routes
    case '/admin/export/json':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $exportController->exportJson();
        break;
    case '/admin/export/csv':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin')
            exit;
        $exportController->exportCsv();
        break;

    // CR Routes
    case '/cr/dashboard':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'cr') {
            header('Location: /login');
            exit;
        }
        require '../src/Views/cr/dashboard.php';
        break;

    // GR Routes
    case '/gr/dashboard':
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'gr') {
            header('Location: /login');
            exit;
        }
        require '../src/Views/gr/dashboard.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
