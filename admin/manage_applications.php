<?php
include('dashboard_header.php');

// Fetch applications
$app_query = "SELECT * FROM career_applications ORDER BY applied_at DESC";
$app_result = mysqli_query($conn, $app_query);

// Avatar color
function avatarColor($name){
    $colors = ['pink', 'blue', 'green', 'orange', 'purple'];
    $index = ord(strtoupper($name[0])) % count($colors);
    return $colors[$index];
}

// Time ago
function timeAgo($datetime){
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return $diff . " seconds ago";
    elseif ($diff < 3600) return round($diff/60) . " minutes ago";
    elseif ($diff < 86400) return round($diff/3600) . " hours ago";
    elseif ($diff < 604800) return round($diff/86400) . " days ago";
    else return date("M j, Y", $time);
}

// Dummy status (change if you have real status column)
function statusClass($id){
    $statuses = ['new','reviewing','shortlisted'];
    return $statuses[$id % 3];
}
?>

<div class="height-100">
    <div class="main-card">
        <div class="main-header">
            <h3>📄 Applications</h3>
        </div>

        <div class="main-table">
            <div class="table-head">
                <span>Applicant</span>
                <span>Position</span>
                <span>Status</span>
                <span>Applied</span>
                <span></span>
            </div>

            <?php while($app = mysqli_fetch_assoc($app_result)): ?>
                <div class="table-row">
                    <div class="client">
                        <div class="avatar <?= avatarColor($app['name']) ?>">
                            <?= strtoupper($app['name'][0]) ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($app['name']) ?></strong>
                            <small><?= htmlspecialchars($app['email']) ?></small>
                        </div>
                    </div>

                    <span><?= htmlspecialchars($app['position']) ?></span>

                    <span class="status <?= statusClass($app['id']) ?>">
                        <?= ucfirst(statusClass($app['id'])) ?>
                    </span>

                    <span><?= timeAgo($app['applied_at']) ?></span>

                    <button class="main-btn"
                        data-name="<?= htmlspecialchars($app['name']) ?>"
                        data-email="<?= htmlspecialchars($app['email']) ?>"
                        data-position="<?= htmlspecialchars($app['position']) ?>"
                        data-date="<?= date("M j, Y g:i A", strtotime($app['applied_at'])) ?>"
                        data-cv="/graphicafix/<?= htmlspecialchars($app['cv']) ?>"                    >
                        View
                    </button>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>
<!-- Enhanced Application Modal -->
<div id="applicationModal" class="modal-overlay">

    <div class="modal-content">

        <!-- Header -->
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon">📄</div>
                Application Details
            </h4>
            <button class="modal-close" id="closeAppModal">&times;</button>
        </div>

        <!-- Body -->
        <div class="modal-body">

            <div class="info-grid">

                <!-- Name -->
                <div class="info-row">
                    <div class="info-icon">👤</div>
                    <div class="info-content">
                        <div class="info-label">Full Name</div>
                        <div class="info-value" id="appModalName"></div>
                    </div>
                </div>

                <!-- Email -->
                <div class="info-row">
                    <div class="info-icon">✉️</div>
                    <div class="info-content">
                        <div class="info-label">Email Address</div>
                        <div class="info-value" id="appModalEmail"></div>
                    </div>
                </div>

                <!-- Position -->
                <div class="info-row">
                    <div class="info-icon">💼</div>
                    <div class="info-content">
                        <div class="info-label">Applied Position</div>
                        <div class="info-value" id="appModalPosition"></div>
                    </div>
                </div>

                <!-- Date -->
                <div class="info-row">
                    <div class="info-icon">📅</div>
                    <div class="info-content">
                        <div class="info-label">Applied On</div>
                        <div class="info-value" id="appModalDate"></div>
                    </div>
                </div>

            </div>

            <!-- CV Section -->
            <div class="message-box">
                <span class="message-label">Curriculum Vitae</span>
                <div class="message-content">
                    <a id="appModalCV" class="btn btn-accent" target="_blank">
                        View / Download CV
                    </a>
                </div>
            </div>

        </div>

        <!-- Footer -->
        <div class="modal-footer">
            <button class="btn btn-secondary" id="closeAppModalBtn">Close</button>
            <a id="appReplyLink" class="btn btn-accent">Reply via Email</a>
        </div>

    </div>
</div>

<script>
const appModal = document.getElementById("applicationModal");

document.querySelectorAll(".main-btn").forEach(btn => {
    btn.addEventListener("click", function(){

        document.getElementById("appModalName").innerText = this.dataset.name;
        document.getElementById("appModalEmail").innerText = this.dataset.email;
        document.getElementById("appModalPosition").innerText = this.dataset.position;
        document.getElementById("appModalDate").innerText = this.dataset.date;

        document.getElementById("appModalCV").href = this.dataset.cv;

        // Mailto link
        document.getElementById("appReplyLink").href =
            "mailto:" + this.dataset.email + "?subject=Regarding your application for " + this.dataset.position;

        appModal.classList.add("active");
    });
});

// Close handlers
document.getElementById("closeAppModal").onclick = () => {
    appModal.classList.remove("active");
};

document.getElementById("closeAppModalBtn").onclick = () => {
    appModal.classList.remove("active");
};

appModal.addEventListener("click", function(e){
    if(e.target === appModal){
        appModal.classList.remove("active");
    }
});
</script>

<?php include('dashboard_footer.php'); ?>