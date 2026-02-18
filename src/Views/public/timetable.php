<?php
// src/Views/public/timetable.php
// Reuse header if possible, or simple HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timetable</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; }
        .grid-container { overflow-x: auto; }
        .grid-table th, .grid-table td { vertical-align: top; min-width: 200px; }
        .class-card {
            border-left: 4px solid #007bff;
            background: #fff;
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 4px;
            font-size: 0.85rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .class-card.conflict { border-left-color: #dc3545; background: #fff5f5; }
        .class-card.cancelled { border-left-color: #6c757d; background: #e9ecef; opacity: 0.7; }
        .time-badge { font-weight: bold; font-size: 0.8em; color: #555; background: #e2e6ea; padding: 2px 4px; border-radius: 3px; }
        .meta-line { display: block; color: #666; font-size: 0.8em; margin-top: 2px; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="/">
        <img src="/assets/images/logo.png" alt="Logo" height="40" class="me-2">
        University Timetable
    </a>
    <div class="d-flex">
        <a href="/login" class="btn btn-outline-light btn-sm">Login (Admin/CR/GR)</a>
    </div>
  </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body py-2">
                    <form method="GET" action="/" class="row g-2 align-items-center">
                        <div class="col-auto">
                            <label class="col-form-label fw-bold">Filter:</label>
                        </div>
                        
                        <!-- Search Inputs -->
                        <div class="col-auto">
                            <input type="text" name="subject_search" class="form-control form-control-sm" placeholder="Subject..." value="<?= htmlspecialchars($_GET['subject_search'] ?? '') ?>">
                        </div>
                        <div class="col-auto">
                            <input type="text" name="teacher_search" class="form-control form-control-sm" placeholder="Teacher..." value="<?= htmlspecialchars($_GET['teacher_search'] ?? '') ?>">
                        </div>

                        <!-- Dropdowns -->
                        <div class="col-auto">
                             <select name="semester_id" class="form-select form-select-sm">
                                 <option value="">All Semesters</option>
                                 <?php foreach($semesters as $sem): ?>
                                    <option value="<?= $sem['id'] ?>" <?= (isset($_GET['semester_id']) && $_GET['semester_id'] == $sem['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sem['batch_name']) ?> - Sem <?= $sem['number'] ?>
                                    </option>
                                 <?php endforeach; ?>
                             </select>
                        </div>

                        <div class="col-auto">
                             <select name="batch_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                 <option value="">All Batches</option>
                                 <?php foreach($batches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= (isset($_GET['batch_id']) && $_GET['batch_id'] == $b['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($b['name']) ?>
                                    </option>
                                 <?php endforeach; ?>
                             </select>
                        </div>

                        <div class="col-auto">
                             <select name="section_id" class="form-select form-select-sm">
                                 <option value="">All Sections</option>
                                 <?php foreach($sections as $s): ?>
                                    <?php if(empty($_GET['batch_id']) || $_GET['batch_id'] == $s['batch_id']): ?>
                                        <option value="<?= $s['id'] ?>" <?= (isset($_GET['section_id']) && $_GET['section_id'] == $s['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($s['batch_name']) ?> - <?= htmlspecialchars($s['name']) ?>
                                        </option>
                                    <?php endif; ?>
                                 <?php endforeach; ?>
                             </select>
                        </div>

                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                            <a href="/" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Timetable Matrix -->
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <span>Full Timetable</span>
                </div>
                <div class="card-body grid-container p-0">
                    <table class="table table-bordered table-striped mb-0 grid-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Day</th>
                                <?php foreach ($allRooms as $room): ?>
                                    <th><?= htmlspecialchars($room['name']) ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($days as $day): ?>
                                <tr>
                                    <td class="fw-bold bg-light align-middle"><?= $day ?></td>
                                    
                                    <?php foreach ($allRooms as $room): ?>
                                        <td>
                                            <?php 
                                                $rid = $room['id'];
                                                if (isset($matrix[$day][$rid])) {
                                                    // Sort by time
                                                    usort($matrix[$day][$rid], fn($a, $b) => strcmp($a['time_slot'], $b['time_slot']));
                                                    
                                                    foreach ($matrix[$day][$rid] as $cls) {
                                                        ?>
                                                        <div class="class-card <?= $cls['status'] ?>">
                                                            <span class="time-badge"><?= htmlspecialchars($cls['time_slot']) ?></span>
                                                            <div class="fw-bold mb-1"><?= htmlspecialchars($cls['subject_name']) ?></div>
                                                            <div class="meta-line">
                                                                <i class="bi bi-person"></i> <?= htmlspecialchars($cls['teacher_name']) ?>
                                                            </div>
                                                            <div class="meta-line text-muted small">
                                                                <?= htmlspecialchars($cls['batch_name']) ?> | 
                                                                <?= htmlspecialchars($cls['section_name']) ?>
                                                            </div>
                                                            <?php if($cls['status'] == 'cancelled'): ?>
                                                                <div class="text-danger fw-bold small text-center">CANCELLED</div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php
                                                    }
                                                }
                                            ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
