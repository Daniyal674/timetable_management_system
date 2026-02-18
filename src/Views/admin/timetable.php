<?php
// src/Views/admin/timetable.php
require '../src/Views/layouts/header.php';
?>
<style>
    .grid-container {
        overflow-x: auto;
    }
    .grid-table th, .grid-table td {
        vertical-align: top;
        min-width: 200px;
    }
    .class-card {
        border-left: 4px solid #007bff;
        background: #f8f9fa;
        padding: 5px;
        margin-bottom: 5px;
        border-radius: 4px;
        font-size: 0.85rem;
        box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        cursor: grab;
        transition: transform 0.1s;
        position: relative;
    }
    .class-card:active { cursor: grabbing; }
    .class-card:hover { transform: scale(1.02); background: #fff; z-index: 10; }
    .class-card.conflict { border-left-color: #dc3545; background: #fff5f5; }
    .class-card.cancelled { border-left-color: #6c757d; background: #e9ecef; opacity: 0.7; }
    .class-card.pending { border-left-color: #ffc107; }
    
    .time-badge { font-weight: bold; font-size: 0.8em; color: #555; background: #e2e6ea; padding: 2px 4px; border-radius: 3px; }
    .meta-line { display: block; color: #666; font-size: 0.8em; margin-top: 2px; }
    
    .drop-zone {
        min-height: 50px;
        height: 100%;
    }
    .drag-over { background-color: #e2e6ea; }
    
    .action-icons {
        position: absolute;
        top: 2px;
        right: 2px;
        font-size: 0.8rem;
        display: none;
    }
    .class-card:hover .action-icons { display: block; }
    .btn-icon { padding: 0 3px; cursor: pointer; color: #6c757d; }
    .btn-icon:hover { color: #000; }
</style>

<div class="row">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Timetable Management</h2>
            <div>
                 <button type="button" class="btn btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#availabilityModal">
                    <i class="bi bi-search"></i> Find Available
                 </button>
                 <div class="dropdown d-inline-block">
                    <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/admin/export/json">JSON</a></li>
                        <li><a class="dropdown-item" href="/admin/export/csv">CSV</a></li>
                    </ul>
                </div>
                <form action="/admin/timetable/clear" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to DELETE ALL timetable records? This cannot be undone.');">
                    <button type="submit" class="btn btn-danger me-2">Clear All</button>
                </form>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                  Upload PDF
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-success">
                <?= $_SESSION['flash_message']; unset($_SESSION['flash_message']); ?>
            </div>
        <?php endif; ?>

        <!-- Extracted Data Review Link (if pending) -->
        <?php if (isset($_SESSION['parsed_data'])): ?>
            <div class="alert alert-info">
                <strong>Parsed Data Ready:</strong> 
                <form action="/admin/timetable/save" method="POST" class="d-inline">
                    <button type="submit" class="btn btn-sm btn-light fw-bold">Confirm & Save to Database</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" action="/admin/timetable" class="row g-2 align-items-center">
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
                        <a href="/admin/timetable" class="btn btn-sm btn-outline-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Saved Database Timetable (Day x Room Matrix) -->
        <div class="card">
            <div class="card-header bg-dark text-white d-flex justify-content-between">
                <span>Full Timetable (Day x Room)</span>
                <small class="text-white-50">Drag and drop to swap slots. Click Icon to Edit/Cancel.</small>
            </div>
            <div class="card-body grid-container p-0">
                <table class="table table-bordered table-hover grid-table mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 100px;">Day</th>
                            <?php foreach ($allRooms as $room): ?>
                                <th><?= htmlspecialchars($room['name']) ?></th>
                            <?php endforeach; ?>
                            <th>Unassigned / Conflict</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($days as $day): ?>
                            <tr>
                                <td class="fw-bold bg-light align-middle"><?= $day ?></td>
                                
                                <!-- Render Columns for Each Room -->
                                <?php foreach ($allRooms as $room): ?>
                                    <td class="drop-zone" data-day="<?= $day ?>" data-room-id="<?= $room['id'] ?>">
                                        <?php 
                                            $rid = $room['id'];
                                            if (isset($matrix[$day][$rid])) {
                                                usort($matrix[$day][$rid], fn($a, $b) => strcmp($a['time_slot'], $b['time_slot']));
                                                
                                                foreach ($matrix[$day][$rid] as $cls) {
                                                    ?>
                                                    <div class="class-card <?= $cls['status'] ?>" draggable="true" data-id="<?= $cls['id'] ?>">
                                                        <div class="action-icons">
                                                            <i class="bi bi-pencil-square btn-icon" onclick="window.location.href='/admin/arrangement/edit/<?= $cls['id'] ?>'" title="Edit"></i>
                                                            <i class="bi bi-x-circle btn-icon text-danger" onclick="toggleStatus(<?= $cls['id'] ?>, 'cancelled')" title="Cancel Class"></i>
                                                        </div>
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
                                                            <div class="text-center"><small><a href="#" onclick="toggleStatus(<?= $cls['id'] ?>, 'scheduled'); return false;">Restore</a></small></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                                
                                <!-- Unassigned Column -->
                                <td>
                                    <?php 
                                        if (isset($matrix[$day][0])) {
                                             foreach ($matrix[$day][0] as $cls) {
                                                ?>
                                                <div class="class-card conflict" draggable="true" data-id="<?= $cls['id'] ?>">
                                                    <span class="badge bg-danger">No Room</span>
                                                    <span class="time-badge"><?= htmlspecialchars($cls['time_slot']) ?></span>
                                                    <div class="fw-bold"><?= htmlspecialchars($cls['subject_name']) ?></div>
                                                    <a href="/admin/arrangement/edit/<?= $cls['id'] ?>" class="btn btn-sm btn-outline-danger mt-1" style="font-size:0.7em">Assign Room</a>
                                                </div>
                                                <?php
                                             }
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Upload Timetable PDF</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="/admin/timetable/upload" method="POST" enctype="multipart/form-data">
          <div class="modal-body">
                <div class="mb-3">
                    <label for="timetable_pdf" class="form-label">Select PDF</label>
                    <input type="file" class="form-control" id="timetable_pdf" name="timetable_pdf" required accept=".pdf">
                </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Upload & Parse</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Availability Modal -->
<div class="modal fade" id="availabilityModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Find Available Rooms</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label small mb-1">Day</label>
                    <select id="searchDay" class="form-select">
                        <option value="">Select Day...</option>
                        <?php foreach($days as $d): ?><option value="<?= $d ?>"><?= $d ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">From</label>
                    <input type="time" id="searchStart" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">To</label>
                    <input type="time" id="searchEnd" class="form-control">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" onclick="searchAvailability()">Search</button>
                </div>
            </div>
            <div id="availabilityResults">
                <p class="text-muted text-center">Select parameters to search.</p>
            </div>
      </div>
    </div>
  </div>
</div>

<script>
// --- Status Toggle Logic ---
function toggleStatus(id, status) {
    if (!confirm('Are you sure you want to change status to ' + status + '?')) return;
    
    fetch('/api/arrangement/status', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id, status: status})
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
        else alert('Error updating status');
    });
}

// --- Availability Search Logic ---
function searchAvailability() {
    const day = document.getElementById('searchDay').value;
    const start = document.getElementById('searchStart').value;
    const end = document.getElementById('searchEnd').value;
    const resDiv = document.getElementById('availabilityResults');
    
    if(!day || !start || !end) { alert('Please select day, start time, and end time'); return; }
    
    resDiv.innerHTML = '<div class="text-center"><div class="spinner-border text-primary"></div> Checking...</div>';
    
    fetch(`/api/arrangement/available-rooms?day=${day}&start=${start}&end=${end}`)
    .then(res => res.json())
    .then(rooms => {
        if(rooms.length === 0) {
            resDiv.innerHTML = '<div class="alert alert-warning">No available rooms found for this slot.</div>';
            return;
        }
        
        let html = '<h6 class="mb-2">Available Rooms:</h6><div class="d-flex flex-wrap gap-2">';
        rooms.forEach(r => {
            html += `<span class="badge bg-success p-2" style="font-size:1em">${r.name} (${r.capacity})</span>`;
        });
        html += '</div>';
        resDiv.innerHTML = html;
    });
}

// --- Drag and Drop Logic ---
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.class-card');
    const dropZones = document.querySelectorAll('.drop-zone');
    
    let draggedCard = null;

    cards.forEach(card => {
        card.addEventListener('dragstart', (e) => {
            draggedCard = card;
            e.dataTransfer.effectAllowed = 'move';
            card.style.opacity = '0.5';
        });

        card.addEventListener('dragend', () => {
            draggedCard = null;
            card.style.opacity = '1';
            dropZones.forEach(zone => zone.classList.remove('drag-over'));
        });
    });

    dropZones.forEach(zone => {
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', () => {
             zone.classList.remove('drag-over');
        });

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            
            // Identify Target
            // Check if dropped on another card (Swap) or empty zone (Move/Swap with empty?)
            // For simplicity, if dropped on a zone, we look for the last card in that zone or just the zone data.
            // If the zone has a card, it's a swap with that card? Or just adding to the zone?
            // "Swap" implies 1-to-1.
            
            // Let's implement: "Swap with whatever is in the target zone".
            // If target zone has multiple classes, ambiguity arises.
            // Let's assume we drop ONTO a card to swap with it, or onto zone to just Move (update room/day/time).
            
            // Accessing the specific drop target element
            const target = e.target.closest('.class-card');
            
            if (target && target !== draggedCard) {
                // Swap with this card
                const sourceId = draggedCard.dataset.id;
                const targetId = target.dataset.id;
                
                if (confirm('Swap these two classes?')) {
                     fetch('/api/arrangement/swap', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({source_id: sourceId, target_id: targetId})
                    })
                    .then(res => res.json())
                    .then(data => {
                        if(data.success) location.reload();
                        else alert('Swap failed: ' + (data.error || 'Unknown error'));
                    });
                }
            } else {
                // Dropped on empty zone (or we consider it a move if no card hit)
                // Actually user asked for SWAPPABLE. Let's stick to Card-to-Card swap for clarity first.
                // Or if dropped on zone, check if we can move?
                // The API I wrote `swapSlots` expects two IDs. To generic move, we need `updateSlot`.
                // For now, let's only support Card-on-Card swap as it matches "make things swappable".
            }
        });
    });
});
</script>

<?php require '../src/Views/layouts/footer.php'; ?>