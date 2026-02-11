<?php

include('dashboard_header.php'); 
    $notice_query = "SELECT * FROM notices ORDER BY `date`";
    $notice_result = mysqli_query($conn, $notice_query);
    $notice_cout = mysqli_num_rows($notice_result);

    $designation = "Designation";
//fetch tasks based on role

    if($role === 'admin'){
        $task_query = "SELECT * FROM tasks WHERE status != 'Completed' ORDER BY due_date ASC";
        $task_result = mysqli_query($conn, $task_query);
        $task_count = mysqli_num_rows($task_result);
    } else if($role === 'user'){
        $task_query = "SELECT * FROM tasks WHERE assigned_to = $user_id AND status != 'Completed' ORDER BY due_date ASC";
        $task_result = mysqli_query($conn, $task_query);
        $task_count = mysqli_num_rows($task_result);
    }


    //fetch resources
    $resource_query = "SELECT * FROM resources ORDER BY created_at DESC";
    $resource_result = mysqli_query($conn, $resource_query);
    $resource_count = mysqli_num_rows($resource_result);


?>
   
   
   
   <style>
            *{
                padding: 0;
                margin: 0;
            }
        </style>
         


    <div class="dashboard-container">
        <!-- Welcome Header -->
        <div class="welcome-text">
            <h1>Welcome back, <?php echo "$name"?>👋</h1>
            <p>Here's what's happening with your projects today.</p>
        </div>

        <div class="row g-4 mb-4">
            <!-- User Info Card -->
            <div class="col-lg-4">
                <div class="user-card">
                    <div class="user-avatar">👨‍💼</div>
                    <div class="user-name"><?php echo "$name"?></div>
                    <div class="user-role"><?php echo "$designation"?></div>
                    
                    <!-- <div class="user-stats">
                        <div class="user-stat">
                            <div class="user-stat-number">24</div>
                            <div class="user-stat-label">Projects</div>
                        </div>
                        <div class="user-stat">
                            <div class="user-stat-number">8</div>
                            <div class="user-stat-label">Active</div>
                        </div>
                        <div class="user-stat">
                            <div class="user-stat-number">16</div>
                            <div class="user-stat-label">Completed</div>
                        </div>
                    </div> -->
                </div>
            </div>

            <!-- Notice Board -->
            <div class="col-lg-8">
                <div class="notice-board">
                    <div class="card-header-custom">
                        <h3>📢 Notice Board</h3>
                        <span class="badge-count"><?php echo "$notice_cout"?> </span>
                    </div>
                    <div class="notices-body">
                    <?php
                        $counter = 1;
                        while($data = mysqli_fetch_assoc($notice_result)){
                            $notice = $data['notice_title'];
                            echo "<div class='notice-single'>";
                            echo "<div class='notice-item'>";
                            echo "<div class='notice-title'> $notice </div>";
                            echo "<div class='notice-time'>Today, 2:30 PM - Don't forget the weekly design review meeting</div>";
                
                            echo "</div>";
                            echo "</div>";
                           
                        }
                    ?>
                    
                    
                </div>
                </div>
            </div>
        </div>

        <!-- Assigned Tasks -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="tasks-card">
                    <div class="card-header-custom">
                        <h3>✅ Your Tasks</h3>
                        <span class="badge-count"><?php echo "$task_count"?> Active</span>
                    </div>

                   <?php while($task = mysqli_fetch_assoc($task_result)): ?>
                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" class="task-status" data-task-id="<?= $task['id'] ?>" <?= $task['status'] === 'Completed' ? 'checked' : '' ?>>
                        </div>
                        <div class="task-content">
                            <div class="task-title"><?= htmlspecialchars($task['title']) ?></div>
                            <div class="task-description"><?= htmlspecialchars($task['description']) ?></div>
                            <div class="task-meta">
                                <span class="task-priority priority-<?= strtolower($task['priority']) ?>"><?= $task['priority'] ?> Priority</span>
                                <span class="task-deadline">⏰ Due: <?= date('M d, Y', strtotime($task['due_date'])) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>

                </div>
            </div>
        </div>

        <!-- Resource Links -->
        <div class="row g-4">
            <div class="col-12">
                <div class="resources-card">
                    <div class="card-header-custom">
                        <h3>📚 Resources & Links</h3>
                        <span class="badge-count"><?php echo "$resource_count"?> Items</span>
                    </div>

                    <div class="resources-list">
                        
                    <div class="resources-list">
                        <?php while($resource = mysqli_fetch_assoc($resource_result)): ?>
                            <a href="<?= htmlspecialchars($resource['link']) ?>" class="resource-item" target="_blank">
                                <div class="resource-icon">📄</div>
                                <div class="resource-info">
                                    <div class="resource-title"><?= htmlspecialchars($resource['title']) ?></div>
                                    <div class="resource-description"><?= htmlspecialchars($resource['description']) ?></div>
                                </div>
                                <span class="resource-link-icon">→</span>
                            </a>
                        <?php endwhile; ?>
                        </div>
                       
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


    <!-- for tasks -->
 <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    $('.task-status').on('change', function() {
        var taskId = $(this).data('task-id');
        var status = $(this).is(':checked') ? 'Completed' : 'Pending';

        // Visual update
        var taskItem = $(this).closest('.task-item');
        if($(this).is(':checked')){
            taskItem.css('opacity', '0.6');
            taskItem.find('.task-title').css('text-decoration', 'line-through');
        } else {
            taskItem.css('opacity', '1');
            taskItem.find('.task-title').css('text-decoration', 'none');
        }

        // AJAX update
        $.ajax({
            url: 'update_task_status.php',
            type: 'POST',
            data: { id: taskId, status: status },
            success: function(response){
                console.log('Server response:', response);
            },
            error: function(xhr, status, error){
                console.error('AJAX Error:', error);
            }
        });
    });
});
</script>

  
    <?php 
include('dashboard_footer.php');
?>




