<?php

include('dashboard_header.php');
// Fetch clients
$client_query = "SELECT * FROM clients ORDER BY created_at DESC";
$client_result = mysqli_query($conn, $client_query);

// Function to assign avatar color class
function avatarColor($name){
    $colors = ['pink', 'blue', 'green', 'orange', 'purple'];
    $index = ord(strtoupper($name[0])) % count($colors);
    return $colors[$index];
}

// Function to display relative time
function timeAgo($datetime){
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return $diff . " seconds ago";
    elseif ($diff < 3600) return round($diff/60) . " minutes ago";
    elseif ($diff < 86400) return round($diff/3600) . " hours ago";
    elseif ($diff < 604800) return round($diff/86400) . " days ago";
    else return date("M j, Y", $time);
}

// Function to randomly assign status (if you don't have it in table)
function statusClass($id){
    $statuses = ['active','pending','completed'];
    return $statuses[$id % 3];
}
?>

<div class="height-100" >
    <div class="main-card">
        <div class="main-header">
            <h3>👥 Clients</h3>
            <button class="add-client">+ Add Client</button>
        </div>

        <div class="main-table">
            <div class="table-head">
                <span>Client</span>
                <span>Company</span>
                <span>Status</span>
                <span>Projects</span>
                <span>Last Activity</span>
                <span></span>
            </div>

            <?php while($client = mysqli_fetch_assoc($client_result)):
                // Example: Count projects for this client
                $projects_query = "SELECT COUNT(*) AS total FROM projects WHERE client_id = {$client['id']}";
                $projects_result = mysqli_query($conn, $projects_query);
                $projects_count = mysqli_fetch_assoc($projects_result)['total'];

                // Last activity (latest created_at of client's projects)
                $last_activity_query = "SELECT created_at FROM projects WHERE client_id = {$client['id']} ORDER BY created_at DESC LIMIT 1";
                $last_activity_result = mysqli_query($conn, $last_activity_query);
                $last_activity = mysqli_fetch_assoc($last_activity_result);
                $last_time = $last_activity ? timeAgo($last_activity['created_at']) : 'N/A';
            ?>
                <div class="table-row">
                    <div class="client">
                        <div class="avatar <?= avatarColor($client['client_name']) ?>">
                            <?= strtoupper($client['client_name'][0]) ?>
                        </div>
                        <div>
                            <strong><?= htmlspecialchars($client['client_name']) ?></strong>
                            <small><?= htmlspecialchars($client['email']) ?></small>
                        </div>
                    </div>
                    <span><?= htmlspecialchars($client['company']) ?></span>
                    <span class="status <?= statusClass($client['id']) ?>"><?= ucfirst(statusClass($client['id'])) ?></span>
                    <span><?= $projects_count ?></span>
                    <span><?= $last_time ?></span>
                    <button class="dots">⋮</button>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php

include('dashboard_footer.php');
?>
