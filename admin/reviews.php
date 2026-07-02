<?php
include 'dashboard_header.php';

$reviews = [];
$result = mysqli_query($conn, "SELECT * FROM reviews ORDER BY created_at DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $reviews[] = $row;
}

function renderStars($rating) {
    return str_repeat('<i class="fas fa-star"></i>', $rating) . str_repeat('<i class="fas fa-star-o"></i>', 5 - $rating);
}
?>
<div class="height-100">
    <div class="main-card">
        <div class="main-header">
            <h3><i class="fas fa-star"></i> Reviews</h3>
            <button class="add-client">+ Add Review</button>
        </div>
        <div class="main-table">
            <div class="table-head">
                <span>Client</span>
                <span>Company</span>
                <span>Rating</span>
                <span>Visible</span>
                <span>Date</span>
                <span></span>
            </div>
            <?php foreach($reviews as $review): ?>
            <div class="table-row">
                <div class="client">
                    <div class="avatar"><?= strtoupper($review['client_name'][0]) ?></div>
                    <div>
                        <strong><?= htmlspecialchars($review['client_name']) ?></strong>
                        <small><?= htmlspecialchars($review['email']) ?></small>
                    </div>
                </div>
                <span><?= htmlspecialchars($review['company']) ?></span>
                <span><?= renderStars($review['rating']) ?></span>

                <!-- Functional toggle — data-id carries the review ID -->
                <label class="switch">
                    <input type="checkbox"
                           class="visibility-toggle"
                           data-id="<?= $review['id'] ?>"
                           <?= $review['visible'] ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>

                <span><?= (new DateTime($review['created_at']))->format('M d, Y H:i') ?></span>
                <button class="dots">⋮</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.visibility-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        const id      = this.dataset.id;
        const visible = this.checked ? 1 : 0;
        const checkbox = this;

        // Optimistic — already flipped visually, revert on failure
        fetch('review_toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'id=' + id + '&visible=' + visible
        })
        .then(r => r.text())
        .then(text => {
            // Strip any accidental HTML before parsing
            const start = text.lastIndexOf('{"success"');
            const json  = JSON.parse(start !== -1 ? text.slice(start) : text);
            if (!json.success) {
                checkbox.checked = !checkbox.checked; // revert
                alert('Failed to update: ' + (json.message || 'unknown error'));
            }
        })
        .catch(function() {
            checkbox.checked = !checkbox.checked; // revert
            alert('Server error. Please try again.');
        });
    });
});
</script>

<?php include('dashboard_footer.php'); ?>