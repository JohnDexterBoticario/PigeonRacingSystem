<?php
/**
 * Create New Race - Admin
 * Handles race scheduling and bird entry registration with sticker codes.
 */

session_start();
require_once "../../Config/database.php"; 

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../Auth/login.php");
    exit();
}

include "../../Includes/sidebar.php"; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Race - Admin</title>
    
    <!-- Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- Custom Project CSS -->
    <link rel="stylesheet" href="../../Assets/Css/createrace.css">

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>

<div class="main-content">
    <div class="container" style="max-width: 1000px;">
        <div class="create-card shadow-sm">
            <header class="mb-5 border-bottom pb-4">
                <h2 class="fw-bold text-dark"><i class="fa-solid fa-flag-checkered text-primary me-2"></i> Schedule New Race</h2>
                <p class="text-muted small mb-0">Define release parameters and register participating birds.</p>
            </header>
            
            <form action="process_create_race.php" method="POST">
                <!-- Race Details Grid -->
                <div class="row g-4 mb-5">
                    <div class="col-md-12">
                        <label class="form-label">Race Name</label>
                        <input type="text" name="race_name" class="form-control" placeholder="e.g. South Summer Race 2026" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Release Point</label>
                        <input type="text" name="release_point" class="form-control" placeholder="e.g. Naga City" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Release Distance (KM)</label>
                        <div class="input-group">
                            <input type="number" step="any" name="distance_km" class="form-control" placeholder="250.00" required>
                            <span class="input-group-text bg-white">KM</span>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Release Date & Time</label>
                        <input type="datetime-local" name="release_datetime" class="form-control" required>
                    </div>
                </div>

                <!-- Bird Entries Section -->
                <div class="entry-section">
                    <h3 class="h5 fw-bold mb-4 d-flex align-items-center">
                        <i class="fa-solid fa-dove text-primary me-2"></i> 
                        Bird Entries & Stickers
                    </h3>
                    
                    <div id="bird-entries-container">
                        <div class="bird-entry-row row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small">Member / Loft</label>
                                <select name="member_user_ids[]" class="member-search form-select" onchange="loadBirds(this)" required>
                                    <option value="">-- Search Member --</option>
                                    <?php 
                                    $members = $conn->query("SELECT id, full_name FROM users WHERE role = 'member' ORDER BY full_name ASC");
                                    while($m = $members->fetch_assoc()): ?>
                                        <option value="<?= $m['id'] ?>">ID: <?= $m['id'] ?> - <?= htmlspecialchars($m['full_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small">Registered Bird</label>
                                <select name="pigeon_ids[]" class="pigeon-select bird-search form-select" required>
                                    <option value="">-- Select Bird --</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small">Sticker Code</label>
                                <input type="text" name="sticker_codes[]" class="form-control" placeholder="ABC-123" required>
                            </div>
                            
                            <div class="col-md-2 text-end">
                                <button type="button" onclick="addRow()" class="btn-add-row shadow-sm">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-5">
                    <button type="submit" name="schedule_race" class="btn btn-primary-race w-100 text-black shadow">
                        <i class="fa-solid fa-calendar-check me-2"></i> SAVE & OPEN RACE ENTRIES
                    </button>
                    <p class="text-center mt-3 small text-muted">
                        <i class="fa-solid fa-lock me-1"></i> Data will be finalized and locked upon scheduling.
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
/**
 * Initialize Select2 filters for searchable dropdowns
 */
$(document).ready(function() { 
    initFilters($('.bird-entry-row')); 
});

function initFilters(row) {
    row.find('.member-search').select2({ placeholder: "-- Search Member --", width: '100%' });
    row.find('.bird-search').select2({ placeholder: "-- Select Bird --", width: '100%' });
}

/**
 * AJAX: Load birds associated with a specific member
 */
function loadBirds(selectElement) {
    const userId = selectElement.value;
    const pigeonSelect = $(selectElement).closest('.bird-entry-row').find('.pigeon-select');
    
    if (userId) {
        fetch(`get_member_birds.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                pigeonSelect.html('<option value="">-- Select Bird --</option>');
                data.forEach(bird => {
                    pigeonSelect.append(new Option(bird.ring_number, bird.id, false, false));
                });
                pigeonSelect.trigger('change');
            });
    }
}

/**
 * Logic: Dynamically add bird entry rows
 */
function addRow() {
    const container = $('#bird-entries-container');
    const $newRow = $('.bird-entry-row').first().clone();
    
    // Clean up Select2 artifacts from the cloned row
    $newRow.find('.select2-container').remove();
    $newRow.find('select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id').val("");
    
    // Reset inputs
    $newRow.find('input').val("");
    
    // Switch plus button to remove button
    $newRow.find('button')
        .html('<i class="fa-solid fa-minus"></i>')
        .removeClass('btn-add-row')
        .addClass('btn-remove-row')
        .attr('onclick', '')
        .off('click')
        .on('click', function() { $(this).closest('.bird-entry-row').remove(); });

    container.append($newRow);
    initFilters($newRow);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>