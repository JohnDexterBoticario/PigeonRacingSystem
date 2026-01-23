<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div id="map" style="height: 400px; width: 100%;"></div>

<script>
    // Initialize map centered at the release point
    var map = L.map('map').setView([<?= $release_lat ?>, <?= $release_lng ?>], 10);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    // Marker for Release Point
    L.marker([<?= $release_lat ?>, <?= $release_lng ?>]).addTo(map)
        .bindPopup('<b>Release Point</b>').openPopup();

    // Loop through lofts (PHP would inject these)
    <?php while($l = $lofts->fetch_assoc()) { ?>
        L.marker([<?= $l['latitude'] ?>, <?= $l['longitude'] ?>]).addTo(map)
            .bindPopup('Loft: <?= $l['loft_name'] ?>');
    <?php } ?>
</script>