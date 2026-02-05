<?php
include('dashboard_header.php');
?>

<div class="height-100">
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
                <div class="stat-number">24</div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">8</div>
                <div class="stat-label">In Progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">5</div>
                <div class="stat-label">Pending Review</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">11</div>
                <div class="stat-label">Completed</div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="tasks-main">
            <!-- Task Board -->
            <div class="task-board">
                <!-- To Do Column -->
                <div class="task-column">
                    <div class="column-header">
                        <span class="column-title">📋 To Do</span>
                        <span class="column-count">6</span>
                    </div>
                    <div class="task-list">
                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-high"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Design TechVibe logo variations</div>
                            <div class="task-description">Create 3 alternative concepts with different color schemes</div>
                            <div class="task-meta">
                                <span>📅 Jan 15</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">JD</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-medium"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Create Instagram templates</div>
                            <div class="task-description">Design 10 customizable post templates for fashion brand</div>
                            <div class="task-meta">
                                <span>📅 Jan 18</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">SM</div>
                                    <div class="assignee-avatar">AK</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-low"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Update brand guidelines</div>
                            <div class="task-description">Revise typography section with new font choices</div>
                            <div class="task-meta">
                                <span>📅 Jan 22</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">MC</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-high"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Review packaging mockups</div>
                            <div class="task-description">Provide feedback on premium skincare designs</div>
                            <div class="task-meta">
                                <span>📅 Jan 14</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">ED</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-medium"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Prepare client presentation</div>
                            <div class="task-description">Create deck for organic cafe project</div>
                            <div class="task-meta">
                                <span>📅 Jan 20</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">JD</div>
                                    <div class="assignee-avatar">SM</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-low"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Export final assets</div>
                            <div class="task-description">Prepare graphics in required formats (SVG, PNG, WebP)</div>
                            <div class="task-meta">
                                <span>📅 Jan 25</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">AK</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- In Progress Column -->
                <div class="task-column">
                    <div class="column-header">
                        <span class="column-title">⚡ In Progress</span>
                        <span class="column-count">4</span>
                    </div>
                    <div class="task-list">
                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-high"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">E-commerce homepage design</div>
                            <div class="task-description">Design responsive landing page with product showcase</div>
                            <div class="task-meta">
                                <span>📅 Jan 16</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">MC</div>
                                    <div class="assignee-avatar">ED</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-medium"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Social media content calendar</div>
                            <div class="task-description">Plan and design content for Q1 marketing campaign</div>
                            <div class="task-meta">
                                <span>📅 Jan 19</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">SM</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-high"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Mobile app UI design</div>
                            <div class="task-description">Create screens for fitness tracking application</div>
                            <div class="task-meta">
                                <span>📅 Jan 17</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">JD</div>
                                    <div class="assignee-avatar">AK</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-medium"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Newsletter template design</div>
                            <div class="task-description">Create responsive email template for monthly newsletter</div>
                            <div class="task-meta">
                                <span>📅 Jan 21</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">MC</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Completed Column -->
                <div class="task-column">
                    <div class="column-header">
                        <span class="column-title">✅ Completed</span>
                        <span class="column-count">3</span>
                    </div>
                    <div class="task-list">
                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-high"></span>
                                <!-- <span class="task-menu">⋮</span> -->
                            </div>
                            <div class="task-title">Brand style guide finalization</div>
                            <div class="task-description">Complete brand guidelines document for client approval</div>
                            <div class="task-meta">
                                <span>✓ Jan 8</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">JD</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-medium"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Business card design</div>
                            <div class="task-description">Design professional business cards for corporate client</div>
                            <div class="task-meta">
                                <span>✓ Jan 7</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">SM</div>
                                </div>
                            </div>
                        </div>

                        <div class="task-card">
                            <div class="task-card-header">
                                <span class="task-priority priority-low"></span>
                                <span class="task-menu">⋮</span>
                            </div>
                            <div class="task-title">Icon set creation</div>
                            <div class="task-description">Design 20 custom icons for mobile application</div>
                            <div class="task-meta">
                                <span>✓ Jan 6</span>
                                <div class="task-assignee">
                                    <div class="assignee-avatar">AK</div>
                                    <div class="assignee-avatar">MC</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="tasks-sidebar">
                <!-- Calendar Widget -->
                <div class="sidebar-card">
                    <div class="sidebar-header">📅 Calendar</div>
                    <div class="mini-calendar">
                        <div class="calendar-month">January 2026</div>
                        <div class="calendar-grid">
                            <div class="calendar-day">S</div>
                            <div class="calendar-day">M</div>
                            <div class="calendar-day">T</div>
                            <div class="calendar-day">W</div>
                            <div class="calendar-day">T</div>
                            <div class="calendar-day">F</div>
                            <div class="calendar-day">S</div>
                            <div class="calendar-day">5</div>
                            <div class="calendar-day">6</div>
                            <div class="calendar-day">7</div>
                            <div class="calendar-day">8</div>
                            <div class="calendar-day">9</div>
                            <div class="calendar-day today">10</div>
                            <div class="calendar-day">11</div>
                            <div class="calendar-day">12</div>
                            <div class="calendar-day">13</div>
                            <div class="calendar-day has-tasks">14</div>
                            <div class="calendar-day has-tasks">15</div>
                            <div class="calendar-day">16</div>
                            <div class="calendar-day">17</div>
                            <div class="calendar-day">18</div>
                            <div class="calendar-day">19</div>
                            <div class="calendar-day has-tasks">20</div>
                            <div class="calendar-day">21</div>
                            <div class="calendar-day">22</div>
                            <div class="calendar-day">23</div>
                            <div class="calendar-day">24</div>
                            <div class="calendar-day">25</div>
                            <div class="calendar-day">26</div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="sidebar-card">
                    <div class="sidebar-header">📊 Recent Activity</div>
                    <div class="activity-item">
                        <div class="activity-icon">✅</div>
                        <div class="activity-content">
                            <div class="activity-title">Task completed: Brand style guide</div>
                            <div class="activity-time">2 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">📝</div>
                        <div class="activity-content">
                            <div class="activity-title">New task added: Newsletter design</div>
                            <div class="activity-time">4 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">💬</div>
                        <div class="activity-content">
                            <div class="activity-title">Comment on: Mobile app UI</div>
                            <div class="activity-time">Yesterday</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon">✅</div>
                        <div class="activity-content">
                            <div class="activity-title">Task completed: Icon set creation</div>
                            <div class="activity-time">2 days ago</div>
                        </div>
                    </div>
                </div>

                <!-- Team Members -->
                <div class="sidebar-card">
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
                    <div class="team-member">
                        <div class="member-avatar">MC</div>
                        <div class="member-info">
                            <div class="member-name">Mike Chen</div>
                            <div class="member-tasks">4 active tasks</div>
                        </div>
                        <div class="member-status"></div>
                    </div>
                    <div class="team-member">
                        <div class="member-avatar">AK</div>
                        <div class="member-info">
                            <div class="member-name">Anna Kim</div>
                            <div class="member-tasks">2 active tasks</div>
                        </div>
                        <div class="member-status"></div>
                    </div>
                </div>
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
include('dashboard_footer.php');
?>