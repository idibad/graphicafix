<?php
    require_once('config.php');
 
    include('dashboard_header.php');

    $notice_query = "SELECT * FROM notices ORDER BY `date`";
    $notice_result = mysqli_query($conn, $notice_query);

    $designation = "Designation";
    ?>
    <style>
            *{
                padding: 0;
                margin: 0;
            }
        </style>
            <!-- Page content-->
            <!-- <div class="height-100">
                <div class="dashboard-main">
                <div class="main-cards">
                    <div class="card">
                        <h4>Your Information</h4>
                        <p>Name: <?php echo "$name"?></p>
                        <p>Phone: <?php echo "$phone"?></p>
                        <p>Email: <?php echo "$email"?></p>
                       
                    </div>
                    <div class="card">
                        <div style="padding: 5px;display: flex; flex-direction: row; align-items: center;">
                            <h4 style="margin: 0;">Important Notices</h4>
                            <a href="#" style="margin-left: auto;">View all</a>
                        </div>

                        <div class="notices-cont">
                            <div class="notices-wrapper">
                                <?php
                                    $counter = 1;
                                    while($data = mysqli_fetch_assoc($notice_result)){
                                        $notice = $data['notice_title'];
                                        echo "<div class='notice-single'>";
                                        echo "<p>$notice</p>";
                                        echo "</div>";

                                        $counter++;
                                        if($counter == 5){
                                        break;
                                        }
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="resources-cont">

                    <h2>Resources</h2>
        <div class="resource-card">
            <div class="resource-info">
                <h3>Project Management Guide</h3>
                <p>An essential guide for managing projects effectively, including best practices and tools.</p>
            </div>
            <a href="path/to/project-management-guide.pdf" download class="btn">View</a>
        </div>
        <div class="resource-card">
            <div class="resource-info">
                <h3>Design Resources</h3>
                <p>A collection of design templates, icons, and stock images to streamline your design process.</p>
            </div>
            <a href="path/to/design-resources.zip" download class="btn">View</a>
        </div>
        <div class="resource-card">
            <div class="resource-info">
                <h3>Code Repository</h3>
                <p>Access our code repository on GitHub for the latest updates and contributions.</p>
            </div>
            <a href="https://github.com/your-repo" target="_blank" class="btn">View</a>
        </div>
        <div class="resource-card">
            <div class="resource-info">
                <h3>Communication Guidelines</h3>
                <p>Guidelines to ensure effective and professional communication within the team and with clients.</p>
            </div>
            <a href="path/to/communication-guidelines.docx" download class="btn">View</a>
        </div>
        <div class="resource-card">
            <div class="resource-info">
                <h3>Training Videos</h3>
                <p>A series of training videos on various tools and techniques used in our projects.</p>
            </div>
            <a href="https://www.youtube.com/playlist?list=your-playlist-id" target="_blank" class="btn">View</a>
        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div> -->

    <style>
        /* .dashboard-main{
            width: 100%;
            margin: auto;

        }
        .main-cards{
            max-width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;

            
        }
        .card{
            width: 49%;
            height: auto;
            background: #fff;
            margin: 10px 0 0 0 ;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 20px;



        }

     .notices-cont {
    position: relative;
    overflow: hidden;
    height: 200px; /* Adjust based on your needs 
    width: 100%;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    padding: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.notices-wrapper {
    display: flex;
    flex-direction: column;
    transition: transform 1s ease;
}

.notice-single {
    width: 100%;
    padding: 10px;
    border-bottom: 1px solid #dee2e6;
    background: #ffffff;
    border-radius: 3px;
    margin-bottom: 10px;
}

.notices-wrapper.clone {
    transition: none;
}


.resources-cont {
    width: 99%;
    margin: 10px auto;
    padding: 40px;
    background-color: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

.resources-cont h2 {
    text-align: center;
    color: #333;
    margin-bottom: 40px;
    font-size: 28px;
}

.resource-card {
    background-color: #fff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: box-shadow 0.3s ease;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.resource-card:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.resource-info {
    flex: 1;
    text-align: left;
}

.resource-info h3 {
    margin: 0 0 10px;
    color: #333;
    font-size: 22px;
}

.resource-info p {
    margin: 0;
    color: #555;
    line-height: 1.6;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    background-color: #4CAF50;
    color: #fff;
    text-decoration: none;
    border-radius: 5px;
    transition: background-color 0.3s ease;
    text-align: right;
}

.btn:hover {
    background-color: #388E3C;
} */
        </style>

        <script>
//         document.addEventListener('DOMContentLoaded', function () {
//     const wrapper = document.querySelector('.notices-wrapper');
//     const notices = document.querySelectorAll('.notice-single');
//     const noticeHeight = notices[0].offsetHeight + 10; // Include margin-bottom
//     let index = 0;

//     // Clone notices to create a seamless loop
//     wrapper.innerHTML += wrapper.innerHTML;

//     setInterval(() => {
//         index++;
//         wrapper.style.transform = `translateY(-${noticeHeight * index}px)`;

//         if (index >= notices.length) {
//             setTimeout(() => {
//                 wrapper.classList.add('clone');
//                 wrapper.style.transform = 'translateY(0)';
//                 index = 0;
//                 setTimeout(() => {
//                     wrapper.classList.remove('clone');
//                 }, 50); // Allow time for the transition property to be reset
//             }, 1000); // Allow time for the last notice to be fully displayed
//         }
//     }, 3000); // Adjust interval timing based on your preference
// });


            </script>



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
                    
                    <div class="user-stats">
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
                    </div>
                </div>
            </div>

            <!-- Notice Board -->
            <div class="col-lg-8">
                <div class="notice-board">
                    <div class="card-header-custom">
                        <h3>📢 Notice Board</h3>
                        <span class="badge-count">4 New</span>
                    </div>
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
                            $counter++;
                            if($counter == 5){
                            break;
                            }
                        }
                    ?>
                    
                    
                </div>
            </div>
        </div>

        <!-- Assigned Tasks -->
        <div class="row g-4 mb-4">
            <div class="col-12">
                <div class="tasks-card">
                    <div class="card-header-custom">
                        <h3>✅ Your Tasks</h3>
                        <span class="badge-count">6 Active</span>
                    </div>

                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" id="task1">
                        </div>
                        <div class="task-content">
                            <div class="task-title">Complete logo variations for TechVibe</div>
                            <div class="task-description">Create 3 alternative logo concepts with different color schemes</div>
                            <div class="task-meta">
                                <span class="task-priority priority-high">High Priority</span>
                                <span class="task-deadline">⏰ Due: Jan 15, 2026</span>
                            </div>
                        </div>
                    </div>

                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" id="task2">
                        </div>
                        <div class="task-content">
                            <div class="task-title">Design Instagram post templates</div>
                            <div class="task-description">Create 10 customizable Instagram templates for fashion brand</div>
                            <div class="task-meta">
                                <span class="task-priority priority-medium">Medium Priority</span>
                                <span class="task-deadline">⏰ Due: Jan 18, 2026</span>
                            </div>
                        </div>
                    </div>

                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" id="task3">
                        </div>
                        <div class="task-content">
                            <div class="task-title">Update brand guidelines document</div>
                            <div class="task-description">Revise typography section with new font choices</div>
                            <div class="task-meta">
                                <span class="task-priority priority-low">Low Priority</span>
                                <span class="task-deadline">⏰ Due: Jan 22, 2026</span>
                            </div>
                        </div>
                    </div>

                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" id="task4">
                        </div>
                        <div class="task-content">
                            <div class="task-title">Review packaging mockups</div>
                            <div class="task-description">Provide feedback on premium skincare packaging designs</div>
                            <div class="task-meta">
                                <span class="task-priority priority-high">High Priority</span>
                                <span class="task-deadline">⏰ Due: Jan 14, 2026</span>
                            </div>
                        </div>
                    </div>

                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" id="task5">
                        </div>
                        <div class="task-content">
                            <div class="task-title">Prepare client presentation</div>
                            <div class="task-description">Create presentation deck for organic cafe project</div>
                            <div class="task-meta">
                                <span class="task-priority priority-medium">Medium Priority</span>
                                <span class="task-deadline">⏰ Due: Jan 20, 2026</span>
                            </div>
                        </div>
                    </div>

                    <div class="task-item">
                        <div class="task-checkbox">
                            <input type="checkbox" id="task6">
                        </div>
                        <div class="task-content">
                            <div class="task-title">Export final assets for web team</div>
                            <div class="task-description">Prepare all graphics in required formats (SVG, PNG, WebP)</div>
                            <div class="task-meta">
                                <span class="task-priority priority-low">Low Priority</span>
                                <span class="task-deadline">⏰ Due: Jan 25, 2026</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Resource Links -->
        <div class="row g-4">
            <div class="col-12">
                <div class="resources-card">
                    <div class="card-header-custom">
                        <h3>📚 Resources & Links</h3>
                        <span class="badge-count">12 Items</span>
                    </div>

                    <div class="resources-list">
                        <a href="#" class="resource-item">
                            <div class="resource-icon">📄</div>
                            <div class="resource-info">
                                <div class="resource-title">Brand Guidelines 2026</div>
                                <div class="resource-description">Complete brand identity guidelines and usage rules</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">🎨</div>
                            <div class="resource-info">
                                <div class="resource-title">Design Assets Library</div>
                                <div class="resource-description">Logos, icons, and graphic elements collection</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">📊</div>
                            <div class="resource-info">
                                <div class="resource-title">Project Templates</div>
                                <div class="resource-description">Reusable templates for social media and marketing</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">🖼️</div>
                            <div class="resource-info">
                                <div class="resource-title">Stock Photo Library</div>
                                <div class="resource-description">High-quality stock images for client projects</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">🎓</div>
                            <div class="resource-info">
                                <div class="resource-title">Tutorial Videos</div>
                                <div class="resource-description">Design tips, tricks, and workflow tutorials</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">📝</div>
                            <div class="resource-info">
                                <div class="resource-title">Client Briefs</div>
                                <div class="resource-description">All active client project requirements and specs</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">💾</div>
                            <div class="resource-info">
                                <div class="resource-title">File Archive</div>
                                <div class="resource-description">Past project files and backup resources</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">🔗</div>
                            <div class="resource-info">
                                <div class="resource-title">Useful Design Links</div>
                                <div class="resource-description">Curated collection of design inspiration sites</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">📱</div>
                            <div class="resource-info">
                                <div class="resource-title">Mobile App Assets</div>
                                <div class="resource-description">UI kits and components for mobile design</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">🎯</div>
                            <div class="resource-info">
                                <div class="resource-title">Marketing Resources</div>
                                <div class="resource-description">Ad templates, email designs, and promotional materials</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">🛠️</div>
                            <div class="resource-info">
                                <div class="resource-title">Design Tools Guide</div>
                                <div class="resource-description">Software tutorials and plugin recommendations</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>

                        <a href="#" class="resource-item">
                            <div class="resource-icon">📚</div>
                            <div class="resource-info">
                                <div class="resource-title">Typography Resources</div>
                                <div class="resource-description">Font libraries and pairing recommendations</div>
                            </div>
                            <span class="resource-link-icon">→</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Task checkbox functionality
        const checkboxes = document.querySelectorAll('.task-checkbox input[type="checkbox"]');
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const taskItem = this.closest('.task-item');
                if (this.checked) {
                    taskItem.style.opacity = '0.6';
                    taskItem.querySelector('.task-title').style.textDecoration = 'line-through';
                } else {
                    taskItem.style.opacity = '1';
                    taskItem.querySelector('.task-title').style.textDecoration = 'none';
                }
            });
        });

        // Resource link click handler
        const resourceLinks = document.querySelectorAll('.resource-item');
        resourceLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const title = this.querySelector('.resource-title').textContent;
                alert(`Opening: ${title}\n\nThis would navigate to the resource page.`);
            });
        });
    </script>
        <?php include('dashboard_footer.php');?>