<?php
require_once('config.php');
include('dashboard_header.php');

$query = "SELECT 
            id, 
            project_name, 
            client_id,
            start_date, 
            end_date, 
            priority, 
            status, 
            progress,
            created_at
          FROM projects 
          ORDER BY id DESC";

$result = mysqli_query($conn, $query);
if (!$result) {
    die("SQL Error: " . mysqli_error($conn));
}
?>

<div class="height-100">

    <!-- <div class="container py-5">
    <div class="row mt-3 gx-3">
    <div class="col-12 col-md-6 col-lg-4 mb-2 mb-md-0">
        <form method="GET" action="" class="d-flex">
            <input class="form-control" type="text" name="keyword" placeholder="Search">
            <input type="submit" name="search" class="main-btn ms-2" value="Search">
        </form>
    </div>

    <div class="col-12 col-md-6 col-lg-8 d-flex justify-content-md-end flex-wrap">
        <a href="project-add.php" class="main-btn me-2 mb-2 mb-md-0">Add New project</a>
    </div>
</div>

    <div class="main-table">
    <table>
        <thead>
            <tr>
                <td>ID</td>
                <td>Project Title</td>
                <td>Client</td>
                <td>Created</td>
                <td>Deadline</td>
                <td>Priority</td>
                <td>Status</td>
                <td>Progress</td>
                <td>Actions</td>
            </tr>
        </thead>

        <tbody>
            <?php while($row = mysqli_fetch_assoc($result)) { ?>

                <tr>
                    <td><?php echo $row['id']; ?></td>

                    <td><?php echo $row['project_name']; ?></td>

                    <td>
                        <?php 
                        // If you want only client_id for now
                        echo $row['client_id']; 
                        ?>
                    </td>

                    <td><?php echo $row['created_at']; ?></td>

                    <td>
                        <?php 
                            echo $row['end_date'] ? $row['end_date'] : "—"; 
                        ?>
                    </td>

                    <td><?php echo $row['priority']; ?></td>

                    <td>
                        <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                            <?php echo $row['status']; ?>
                        </span>
                    </td>

                    <td><?php echo $row['progress']; ?>%</td>

                    <td>
                        <a href="edit_project.php?id=<?php echo $row['id']; ?>" class="btn-sm edit">Edit</a>
                        <a href="delete_project.php?id=<?php echo $row['id']; ?>" class="btn-sm delete">Delete</a>
                    </td>
                </tr>

            <?php } ?>
        </tbody>

    </table>
</div> -->

<style>

 .projects-container {
            padding: 30px;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-title-section h1 {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .page-title-section p {
            color: #666;
            font-size: 15px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn-primary-custom {
            background: var(--accent);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(181, 255, 107, 0.4);
        }

        .btn-secondary-custom {
            background: white;
            color: #666;
            border: 2px solid #e0e0e0;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-secondary-custom:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 107, 107, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #666;
            font-weight: 500;
        }

        .stat-change {
            font-size: 12px;
            color: #10b981;
            margin-top: 8px;
        }

        .stat-change.negative {
            color: #ef4444;
        }

        /* Filter and Search Section */
        .filter-section {
            background: white;
            padding: 20px 25px;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 40px 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--accent);
        }

        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 10px 20px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #666;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
            font-weight: 500;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }

        /* Projects Grid */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
            
        }

        .project-card {
            padding: 0 0 10px 0;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .project-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15);
        }

        .project-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
        }

        .project-card:nth-child(2) .project-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .project-card:nth-child(3) .project-header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .project-card:nth-child(4) .project-header {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
        }

        .project-card:nth-child(5) .project-header {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .project-card:nth-child(6) .project-header {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
        }

        .project-status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .project-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .project-client {
            font-size: 13px;
            opacity: 0.9;
        }

        .project-body {
            padding: 20px;
        }

        .project-description {
            font-size: 14px;
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .project-progress {
            margin-bottom: 20px;
        }

        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .progress-label {
            font-size: 13px;
            color: #666;
            font-weight: 600;
        }

        .progress-percentage {
            font-size: 13px;
            color: var(--accent);
            font-weight: 700;
        }

        .progress-bar-custom {
            width: 100%;
            height: 8px;
            background: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: var(--accent);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .project-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #f0f0f0;
        }

        .project-deadline {
            font-size: 13px;
            color: #666;
        }

        .project-team {
            display: flex;
            gap: -8px;
        }

        .team-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            color: white;
            font-weight: 600;
            margin-left: -8px;
        }

        .team-avatar:first-child {
            margin-left: 0;
        }

        .team-avatar:nth-child(2) {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .team-avatar:nth-child(3) {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #333;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #666;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .projects-container {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-actions {
                width: 100%;
            }

            .btn-primary-custom,
            .btn-secondary-custom {
                flex: 1;
            }

            .projects-grid {
                grid-template-columns: 1fr;
            }

            .filter-section {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                width: 100%;
            }

            .filter-tabs {
                width: 100%;
            }

            .filter-tab {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Main Projects Section -->
    <div class="projects-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title-section">
                <h1>Projects</h1>
                <p>Manage and track all your design projects</p>
            </div>
            <div class="header-actions">
                <button class="btn-secondary-custom">Export</button>
                <button class="btn-primary-custom">+ New Project</button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number">32</div>
                <div class="stat-label">Total Projects</div>
                <div class="stat-change">↑ 12% from last month</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⚡</div>
                <div class="stat-number">14</div>
                <div class="stat-label">Active Projects</div>
                <div class="stat-change">↑ 8% increase</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number">18</div>
                <div class="stat-label">Completed</div>
                <div class="stat-change">↑ 15% this month</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">⏰</div>
                <div class="stat-number">5</div>
                <div class="stat-label">Due This Week</div>
                <div class="stat-change negative">↑ 2 urgent</div>
            </div>
        </div>

        <!-- Filter and Search -->
        <div class="filter-section">
            <div class="search-box">
                <input type="text" placeholder="Search projects...">
                <span class="search-icon">🔍</span>
            </div>
            <div class="filter-tabs">
                <button class="filter-tab active">All</button>
                <button class="filter-tab">Active</button>
                <button class="filter-tab">Completed</button>
                <button class="filter-tab">On Hold</button>
            </div>
        </div>

        <!-- Projects Grid -->
        <div class="projects-grid">
            <!-- Project Card 1 -->
            <div class="project-card">
                <div class="project-header">
                    <span class="project-status-badge">In Progress</span>
                    <div class="project-name">TechVibe Branding</div>
                    <div class="project-client">Client: TechVibe Inc.</div>
                </div>
                <div class="project-body">
                    <div class="project-description">
                        Complete brand identity design for innovative tech startup including logo, guidelines, and marketing collateral.
                    </div>
                    <div class="project-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage">75%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: 75%"></div>
                        </div>
                    </div>
                    <div class="project-meta">
                        <div class="project-deadline">⏰ Due: Jan 15, 2026</div>
                        <div class="project-team">
                            <div class="team-avatar">JD</div>
                            <div class="team-avatar">SM</div>
                            <div class="team-avatar">AK</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Card 2 -->
            <div class="project-card">
                <div class="project-header">
                    <span class="project-status-badge">In Progress</span>
                    <div class="project-name">E-Commerce Platform</div>
                    <div class="project-client">Client: ShopHub</div>
                </div>
                <div class="project-body">
                    <div class="project-description">
                        Responsive online store design with seamless checkout flow and modern UI/UX principles.
                    </div>
                    <div class="project-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage">60%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: 60%"></div>
                        </div>
                    </div>
                    <div class="project-meta">
                        <div class="project-deadline">⏰ Due: Jan 20, 2026</div>
                        <div class="project-team">
                            <div class="team-avatar">MC</div>
                            <div class="team-avatar">ED</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Card 3 -->
            <div class="project-card">
                <div class="project-header">
                    <span class="project-status-badge">Completed</span>
                    <div class="project-name">Organic Cafe Branding</div>
                    <div class="project-client">Client: Green Leaf Cafe</div>
                </div>
                <div class="project-body">
                    <div class="project-description">
                        Modern brand identity for eco-friendly cafe featuring earthy tones and natural aesthetics.
                    </div>
                    <div class="project-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage">100%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="project-meta">
                        <div class="project-deadline">✅ Completed: Jan 8, 2026</div>
                        <div class="project-team">
                            <div class="team-avatar">JD</div>
                            <div class="team-avatar">SM</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Card 4 -->
            <div class="project-card">
                <div class="project-header">
                    <span class="project-status-badge">In Progress</span>
                    <div class="project-name">Social Media Campaign</div>
                    <div class="project-client">Client: Fashion Forward</div>
                </div>
                <div class="project-body">
                    <div class="project-description">
                        Engaging social media templates and content strategy for fashion brand's spring collection.
                    </div>
                    <div class="project-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage">45%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: 45%"></div>
                        </div>
                    </div>
                    <div class="project-meta">
                        <div class="project-deadline">⏰ Due: Jan 18, 2026</div>
                        <div class="project-team">
                            <div class="team-avatar">AK</div>
                            <div class="team-avatar">MC</div>
                            <div class="team-avatar">ED</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Card 5 -->
            <div class="project-card">
                <div class="project-header">
                    <span class="project-status-badge">In Progress</span>
                    <div class="project-name">Premium Packaging Design</div>
                    <div class="project-client">Client: LuxeSkin</div>
                </div>
                <div class="project-body">
                    <div class="project-description">
                        Luxury packaging design with minimalist aesthetics and premium materials for skincare line.
                    </div>
                    <div class="project-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage">85%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: 85%"></div>
                        </div>
                    </div>
                    <div class="project-meta">
                        <div class="project-deadline">⏰ Due: Jan 12, 2026</div>
                        <div class="project-team">
                            <div class="team-avatar">JD</div>
                            <div class="team-avatar">SM</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Project Card 6 -->
            <div class="project-card">
                <div class="project-header">
                    <span class="project-status-badge">On Hold</span>
                    <div class="project-name">Corporate Website Redesign</div>
                    <div class="project-client">Client: Business Corp</div>
                </div>
                <div class="project-body">
                    <div class="project-description">
                        Professional website redesign with improved UX and brand consistency for corporate client.
                    </div>
                    <div class="project-progress">
                        <div class="progress-header">
                            <span class="progress-label">Progress</span>
                            <span class="progress-percentage">30%</span>
                        </div>
                        <div class="progress-bar-custom">
                            <div class="progress-fill" style="width: 30%"></div>
                        </div>
                    </div>
                    <div class="project-meta">
                        <div class="project-deadline">⏰ Due: Feb 5, 2026</div>
                        <div class="project-team">
                            <div class="team-avatar">MC</div>
                            <div class="team-avatar">AK</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filter functionality
        const filterTabs = document.querySelectorAll('.filter-tab');
        const projectCards = document.querySelectorAll('.project-card');

        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                filterTabs.forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');

                const filter = this.textContent.toLowerCase();

                // Show/hide projects based on filter
                projectCards.forEach(card => {
                    const status = card.querySelector('.project-status-badge').textContent.toLowerCase();
                    
                    if (filter === 'all') {
                        card.style.display = 'block';
                    } else if (filter === 'active' && status === 'in progress') {
                        card.style.display = 'block';
                    } else if (filter === 'completed' && status === 'completed') {
                        card.style.display = 'block';
                    } else if (filter === 'on hold' && status === 'on hold') {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });

        // Search functionality
        const searchInput = document.querySelector('.search-box input');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();

            projectCards.forEach(card => {
                const projectName = card.querySelector('.project-name').textContent.toLowerCase();
                const clientName = card.querySelector('.project-client').textContent.toLowerCase();
                const description = card.querySelector('.project-description').textContent.toLowerCase();

                if (projectName.includes(searchTerm) || clientName.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Project card click handler
        projectCards.forEach(card => {
            card.addEventListener('click', function() {
                const projectName = this.querySelector('.project-name').textContent;
                alert(`Opening project: ${projectName}\n\nThis would navigate to the project details page.`);
                // window.location.href = `/projects/${projectId}`;
            });
        });

        // New Project button
        document.querySelector('.btn-primary-custom').addEventListener('click', function() {
            alert('Opening New Project form...\n\nThis would open a modal or navigate to a project creation page.');
        });

        // Export button
        document.querySelector('.btn-secondary-custom').addEventListener('click', function() {
            alert('Exporting projects data...\n\nThis would trigger a CSV/PDF download.');
        });
    </script>
</div>

<?php include('dashboard_footer.php'); ?>
