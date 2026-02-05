<?php
include('dashboard_header.php');
?>
<div class="height-100">

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

        <div class="table-row">
            <div class="client">
                <div class="avatar">A</div>
                <div>
                    <strong>Ali Khan</strong>
                    <small>ali@techvibe.com</small>
                </div>
            </div>
            <span>TechVibe</span>
            <span class="status active">Active</span>
            <span>4</span>
            <span>2h ago</span>
            <button class="dots">⋮</button>
        </div>

        <div class="table-row">
            <div class="client">
                <div class="avatar pink">S</div>
                <div>
                    <strong>Sara Malik</strong>
                    <small>sara@zarmall.com</small>
                </div>
            </div>
            <span>Zarmall</span>
            <span class="status pending">Pending</span>
            <span>2</span>
            <span>Yesterday</span>
            <button class="dots">⋮</button>
        </div>

        <div class="table-row">
            <div class="client">
                <div class="avatar blue">R</div>
                <div>
                    <strong>Rizwan</strong>
                    <small>riz@matacon.pk</small>
                </div>
            </div>
            <span>Matacon</span>
            <span class="status completed">Completed</span>
            <span>7</span>
            <span>3 days ago</span>
            <button class="dots">⋮</button>
        </div>
    </div>
</div>

</div>
<?php
include('dashboard_footer.php');
?>