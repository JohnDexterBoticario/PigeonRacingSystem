<?php
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
    <title>Create New Race - Admin</title>
    <link rel="stylesheet" href="../../Assets/Css/nav.css">
    <link rel="stylesheet" href="../../Assets/Css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <style>
        .main-content { margin-left: 260px; padding: 40px; }
        .card { background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
        .input-group input, .input-group select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; }
        .btn-primary { background: #8a6b49; color: white; border: none; padding: 15px; border-radius: 8px; width: 100%; font-weight: bold; cursor: pointer; }
        .bird-entry-row { display: grid; grid-template-columns: 2fr 1.5fr 1.5fr auto; gap: 15px; margin-bottom: 15px; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container" style="max-width: 950px; margin: auto;">
        <div class="card">
            <h2><i class="fa-solid fa-flag-checkered" style="color: #8a6b49;"></i> Create New Race</h2>
            
            <form action="process_create_race.php" method="POST">
                <div class="input-group">
                    <label>Race Name</label>
                    <input type="text" name="race_name" placeholder="e.g. South Summer Race 2026" required>
                </div>

                <div class="input-group">
                    <label>Release Point (Location Name)</label>
                    <input type="text" name="release_point" placeholder="e.g. Naga City" required>
                </div>

                <div class="input-group">
                    <label>Release Distance (Kilometers)</label>
                    <input type="number" step="any" name="distance_km" required placeholder="e.g. 250.450">
                    <small style="color: #666;">This distance maps to 'distance_km' in your database.</small>
                </div>

                <div class="input-group">
                    <label>Release Date & Time</label>
                    <input type="datetime-local" name="release_datetime" required>
                </div>

                <div style="border-top: 2px solid #eee; margin-top: 30px; padding-top: 20px;">
                    <h3><i class="fa-solid fa-dove"></i> Race Entries & Stickers</h3>
                    <div id="bird-entries-container">
                        <div class="bird-entry-row">
                            <select name="member_user_ids[]" class="member-search" onchange="loadBirds(this)" required>
                                <option value="">-- Search Member --</option>
                                <?php 
                                $members = $conn->query("SELECT id, full_name FROM users WHERE role = 'member' ORDER BY full_name ASC");
                                while($m = $members->fetch_assoc()): ?>
                                    <option value="<?= $m['id'] ?>">ID: <?= $m['id'] ?> - <?= htmlspecialchars($m['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>

                            <select name="pigeon_ids[]" class="pigeon-select bird-search" required>
                                <option value="">-- Select Bird --</option>
                            </select>

                            <input type="text" name="sticker_codes[]" placeholder="Sticker Code" required>
                            
                            <button type="button" onclick="addRow()" style="background: #27ae60; color: white; border: none; padding: 10px 15px; border-radius: 8px; cursor: pointer;">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" name="schedule_race" class="btn-primary">
                        <i class="fa-solid fa-calendar-check"></i> SAVE & SCHEDULE RACE
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() { 
    initFilters($('.bird-entry-row')); 
});

function initFilters(row) {
    row.find('.member-search').select2({ placeholder: "-- Search Member --", width: '100%' });
    row.find('.bird-search').select2({ placeholder: "-- Select Bird --", width: '100%' });
}

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

function addRow() {
    const container = $('#bird-entries-container');
    const $newRow = $('.bird-entry-row').first().clone();
    
    // Clean up Select2 artifacts from the cloned row
    $newRow.find('.select2-container').remove();
    $newRow.find('select').removeClass('select2-hidden-accessible').removeAttr('data-select2-id').val("");
    
    // Reset inputs
    $newRow.find('input').val("");
    
    // Change plus button to minus button for removal
    $newRow.find('button')
        .html('<i class="fa-solid fa-minus"></i>')
        .css('background', '#e74c3c')
        .attr('onclick', '')
        .off('click')
        .on('click', function() { $(this).parent().remove(); });

    container.append($newRow);
    initFilters($newRow);
}
</script>
</body>
</html>