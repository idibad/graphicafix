<?php

include('dashboard_header.php');

// Fetch reviews
$reviews = [];
$result = mysqli_query($conn, "SELECT * FROM reviews ORDER BY created_at DESC");
while ($row = mysqli_fetch_assoc($result)) {
    $reviews[] = $row;
}

// Function to render star rating
function renderStars($rating) {
    $fullStars = str_repeat('★', $rating);
    $emptyStars = str_repeat('☆', 5 - $rating);
    return $fullStars . $emptyStars;
}
?>

<div class="height-100" >
    <div class="main-card">
        <div class="main-header">
            <h3>⭐ Reviews</h3>
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
                    <div class="avatar"><?php echo strtoupper($review['client_name'][0]); ?></div>
                    <div>
                        <strong><?php echo htmlspecialchars($review['client_name']); ?></strong>
                        <small><?php echo htmlspecialchars($review['email']); ?></small>
                    </div>
                </div>
                <span><?php echo htmlspecialchars($review['company']); ?></span>
                <span><?php echo renderStars($review['rating']); ?></span>

                <!-- Toggle -->
                <label class="switch">
                    <input type="checkbox" <?php echo $review['visible'] ? 'checked' : ''; ?>>
                    <span class="slider"></span>
                </label>

                <span><?php 
                    $date = new DateTime($review['created_at']);
                    echo $date->format('M d, Y H:i');
                ?></span>

                <button class="dots">⋮</button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php 
include('dashboard_footer.php');
?>
