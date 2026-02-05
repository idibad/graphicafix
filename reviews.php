<?php
    require_once('config.php');
    include('dashboard_header.php');
?>
        <!-- Page content-->
        <div class="height-100">
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

        <div class="table-row">
            <div class="client">
                <div class="avatar">A</div>
                <div>
                    <strong>Ali Khan</strong>
                    <small>ali@techvibe.com</small>
                </div>
            </div>
            <span>TechVibe</span>
            <span>★★★★★</span>

            <!-- Toggle -->
            <label class="switch">
                <input type="checkbox" checked>
                <span class="slider"></span>
            </label>

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
            <span>★★★★☆</span>

            <label class="switch">
                <input type="checkbox">
                <span class="slider"></span>
            </label>

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
            <span>★★★☆☆</span>

            <label class="switch">
                <input type="checkbox" checked>
                <span class="slider"></span>
            </label>

            <span>3 days ago</span>
            <button class="dots">⋮</button>
        </div>
    </div>
</div>

</div>

       
    </div>

<?php include('dashboard_footer.php');?>