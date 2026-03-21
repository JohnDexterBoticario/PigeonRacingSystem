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
 * Initialize Select2 on page load
 */
$(document).ready(function() { 
    $('.member-search, .bird-search').each(function() {
        initSelect2($(this));
    });
});

/**
 * Helper to initialize Select2
 */
function initSelect2(element) {
    element.select2({
        placeholder: element.hasClass('member-search') ? "-- Search Member --" : "-- Select Bird --",
        width: '100%',
        dropdownParent: $('body') 
    });
}

/**
 * AJAX: Load ONLY the birds belonging to the selected member
 */
function loadBirds(selectElement) {
    const userId = selectElement.value;
    const $row = $(selectElement).closest('.bird-entry-row');
    const $pigeonSelect = $row.find('.pigeon-select');
    
    // Clear the bird dropdown immediately when member changes
    $pigeonSelect.empty().append(new Option('-- Select Bird --', '', true, true));

    if (userId) {
        // Show a "Loading..." state in the bird dropdown
        $pigeonSelect.append(new Option('Loading birds...', '', false, false));
        $pigeonSelect.trigger('change');

        fetch(`get_member_birds.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                // Clear "Loading..." and actual list
                $pigeonSelect.empty();
                $pigeonSelect.append(new Option('-- Select Bird --', '', true, true));

                if (data.length > 0) {
                    data.forEach(bird => {
                        // Create option: Text is Ring Number, Value is Bird ID
                        $pigeonSelect.append(new Option(bird.ring_number, bird.id, false, false));
                    });
                } else {
                    $pigeonSelect.append(new Option('No birds registered for this member', '', false, false));
                }
                
                // Refresh Select2 to show new data
                $pigeonSelect.trigger('change');
            })
            .catch(error => {
                console.error('Error fetching birds:', error);
                $pigeonSelect.empty().append(new Option('Error loading birds', '', true, true)).trigger('change');
            });
    } else {
        $pigeonSelect.trigger('change');
    }
}

function addRow() {
    const container = $('#bird-entries-container');
    // We clone the very last row so it carries over the current Member selection
    const $lastRow = $('.bird-entry-row').last();
    const $newRow = $lastRow.clone();

    // 1. Clean up Select2 artifacts from the clone
    $newRow.find('.select2-container').remove(); 
    $newRow.find('select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id').removeAttr('aria-hidden').removeAttr('tabindex');

    // 2. Clear ONLY the Bird and Sticker fields
    // We keep the Member ID so the admin doesn't have to search for the same member again
    $newRow.find('.pigeon-select').val(""); 
    $newRow.find('input[name="sticker_codes[]"]').val("");

    // 3. Transform the Plus button into a Red Minus (Remove) button
    const $btn = $newRow.find('button');
    $btn.html('<i class="fa-solid fa-minus"></i>')
        .removeClass('btn-add-row').addClass('btn-remove-row')
        .css('background-color', '#dc3545')
        .attr('onclick', '') 
        .off('click')
        .on('click', function() {
            $(this).closest('.bird-entry-row').remove();
        });

    // 4. Append to the container
    container.append($newRow);

    // 5. Re-initialize Select2 for the new row
    initFilters($newRow);
    $('form').on('submit', function(e) {
    let birds = [];
    let duplicate = false;
    $('.pigeon-select').each(function() {
        let val = $(this).val();
        if(val && birds.includes(val)) {
            duplicate = true;
        }
        birds.push(val);
    });

    if(duplicate) {
        alert("Error: You have selected the same bird twice!");
        e.preventDefault();
    }
});
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>