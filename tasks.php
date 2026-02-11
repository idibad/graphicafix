<?php
include('dashboard_header.php');

// Get task counts by status
$task_stats_query = "
    SELECT 
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
        SUM(CASE WHEN status = 'Pending Review' THEN 1 ELSE 0 END) AS pending_review,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed
    FROM tasks
";
$task_stats_result = mysqli_query($conn, $task_stats_query);
$task_stats = mysqli_fetch_assoc($task_stats_result);

$total_tasks = $task_stats['total_tasks'];
$in_progress = $task_stats['in_progress'];
$pending_review = $task_stats['pending_review'];
$completed = $task_stats['completed'];


// Fetch tasks grouped by status
$tasks_query = "SELECT * FROM tasks ORDER BY due_date ASC";
$tasks_result = mysqli_query($conn, $tasks_query);

// Group tasks into arrays by status
$tasks_by_status = [
    'To Do' => [],
    'In Progress' => [],
    'Completed' => []
];

while ($task = mysqli_fetch_assoc($tasks_result)) {
    switch ($task['status']) {
        case 'In Progress':
            $tasks_by_status['In Progress'][] = $task;
            break;
        case 'Completed':
            $tasks_by_status['Completed'][] = $task;
            break;
        default:
            $tasks_by_status['To Do'][] = $task;
            break;
    }
}

// calendar data 
// Get current month and year
$month = date('m'); // e.g., 02
$year = date('Y');  // e.g., 2026

// Fetch tasks due in this month
$task_dates_query = "
    SELECT due_date 
    FROM tasks 
    WHERE MONTH(due_date) = $month AND YEAR(due_date) = $year
";
$task_dates_result = mysqli_query($conn, $task_dates_query);

// Store task dates in an array for quick lookup
$task_dates = [];
while ($row = mysqli_fetch_assoc($task_dates_result)) {
    $task_dates[] = date('j', strtotime($row['due_date'])); // store day number only
}


//RECENT ACTIVITY
$activity_query = "
    SELECT title, status, updated_at, created_at
    FROM tasks
    ORDER BY GREATEST(UNIX_TIMESTAMP(updated_at), UNIX_TIMESTAMP(created_at)) DESC
    LIMIT 10
";
$activity_result = mysqli_query($conn, $activity_query);

// Function to calculate relative time
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return $diff . " seconds ago";
    elseif ($diff < 3600) return round($diff/60) . " minutes ago";
    elseif ($diff < 86400) return round($diff/3600) . " hours ago";
    elseif ($diff < 604800) return round($diff/86400) . " days ago";
    else return date("M j, Y", $time);
}

?>

<div class="height-100" >
    <div class="tasks-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1>Task Management</h1>
                <p>Organize and track your tasks efficiently</p>
            </div>
            <div class="header-actions">
                <button class="btn-secondary-custom">⚙️ Settings</button>
                <button class="btn-primary-custom">+ New Task</button>
            </div>
        </div>

        <!-- Stats Cards -->
       <div class="stats-row">
        <div class="stat-card">
            <div class="stat-number"><?= $total_tasks ?></div>
            <div class="stat-label">Total Tasks</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $in_progress ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $pending_review ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
        <div class="stat-card">
            <div class="stat-number"><?= $completed ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>


        <!-- Main Content -->
        <div class="tasks-main">
            <!-- Task Board -->
            <div class="task-board">
    <?php foreach ($tasks_by_status as $status => $tasks): ?>
        <div class="task-column">
            <div class="column-header">
                <span class="column-title">
                    <?php
                        echo $status === 'To Do' ? '📋 To Do' : ($status === 'In Progress' ? '⚡ In Progress' : '✅ Completed');
                    ?>
                </span>
                <span class="column-count"><?= count($tasks) ?></span>
            </div>
            <div class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <div class="task-card" draggable="true">
                        <div class="task-card-header">
                            <span class="task-priority priority-<?= strtolower($task['priority']) ?>"></span>
                            <span class="task-menu">⋮</span>
                        </div>
                        <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                        <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                        <div class="task-meta">
                            <span>📅 <?= date('M d', strtotime($task['due_date'])) ?></span>
                            <div class="task-assignee">
                                <?php
                                $assignees = explode(',', $task['assigned_to']); // assuming comma-separated user initials
                                foreach ($assignees as $assignee): ?>
                                    <div class="assignee-avatar"><?= htmlspecialchars(trim($assignee)) ?></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>


            <!-- Sidebar -->
            <div class="tasks-sidebar">
                <!-- Calendar Widget -->
                <div class="sidebar-card">
                    <div class="sidebar-header">📅 Calendar</div>
                    <div class="mini-calendar">
                        <?php 
                        $current_month_name = date('F', mktime(0, 0, 0, $month, 1)); 
                        echo "<div class='calendar-month'>$current_month_name $year</div>"; 
                        ?>

                        <div class="calendar-grid">
                            <?php
                            // Days of the week headers
                            $daysOfWeek = ['S','M','T','W','T','F','S'];
                            foreach ($daysOfWeek as $day) {
                                echo "<div class='calendar-day'>$day</div>";
                            }

                            // First day of month
                            $first_day_of_month = mktime(0, 0, 0, $month, 1, $year);
                            $total_days = date('t', $first_day_of_month); // total days in month
                            $start_day = date('w', $first_day_of_month);  // day of week (0=Sun..6=Sat)

                            // Empty cells for first week
                            for($i = 0; $i < $start_day; $i++){
                                echo "<div class='calendar-day empty'></div>";
                            }

                            // Loop through each day of the month
                            for($day = 1; $day <= $total_days; $day++){
                                $classes = 'calendar-day';
                                if(in_array($day, $task_dates)){
                                    $classes .= ' has-tasks';
                                }
                                if($day == date('j') && $month == date('m') && $year == date('Y')){
                                    $classes .= ' today';
                                }
                                echo "<div class='$classes'>$day</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>


                <!-- Recent Activity -->
                <div class="sidebar-card">
                    <div class="sidebar-header">📊 Recent Activity</div>

                    <?php while($activity = mysqli_fetch_assoc($activity_result)): ?>
                        <?php
                            // Determine icon and message
                            if($activity['status'] === 'Completed'){
                                $icon = "✅";
                                $title = "Task completed: " . htmlspecialchars($activity['title']);
                                $time = timeAgo($activity['updated_at']);
                            } else {
                                $icon = "📝";
                                $title = "New task added: " . htmlspecialchars($activity['title']);
                                $time = timeAgo($activity['created_at']);
                            }
                        ?>
                        <div class="activity-item">
                            <div class="activity-icon"><?= $icon ?></div>
                            <div class="activity-content">
                                <div class="activity-title"><?= $title ?></div>
                                <div class="activity-time"><?= $time ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>


                <!-- Team Members -->
                <!-- <div class="sidebar-card">
                    <div class="sidebar-header">👥 Team Members</div>
                    <div class="team-member">
                        <div class="member-avatar">JD</div>
                        <div class="member-info">
                            <div class="member-name">John Doe</div>
                            <div class="member-tasks">5 active tasks</div>
                        </div>
                        <div class="member-status"></div>
                    </div>
                    <div class="team-member">
                        <div class="member-avatar">SM</div>
                        <div class="member-info">
                            <div class="member-name">Sarah Mitchell</div>
                            <div class="member-tasks">3 active tasks</div>
                        </div>
                        <div class="member-status"></div>
                    </div>
                    
                </div> -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
           let draggedElement = null;

const taskCards = document.querySelectorAll('.task-card');
const taskLists = document.querySelectorAll('.task-list');

/* Make cards draggable */
taskCards.forEach(card => {
    card.setAttribute('draggable', true);

    card.addEventListener('dragstart', function () {
        draggedElement = this;
        this.classList.add('dragging');
    });

    card.addEventListener('dragend', function () {
        this.classList.remove('dragging');
        draggedElement = null;
    });
});

/* Enable drop zones */
taskLists.forEach(list => {

    list.addEventListener('dragover', function (e) {
        e.preventDefault(); // REQUIRED for drop to work

        const afterElement = getDragAfterElement(list, e.clientY);
        if (afterElement == null) {
            list.appendChild(draggedElement);
        } else {
            list.insertBefore(draggedElement, afterElement);
        }
    });

    list.addEventListener('drop', function () {
        this.classList.remove('drag-over');
    });

    list.addEventListener('dragenter', function () {
        this.classList.add('drag-over');
    });

    list.addEventListener('dragleave', function () {
        this.classList.remove('drag-over');
    });
});

/* Find where to insert while dragging */
function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.task-card:not(.dragging)')];

    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
}

    </script>

</div>
<?php
?>