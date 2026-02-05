<?php
include('dashboard_header.php');
?>
<style>
.services-page {
    padding: 30px;
    max-width: 1300px;
    margin: auto;
    font-family: system-ui;
}

/* Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}
.page-header h2 {
    margin: 0;
}
.page-header p {
    color: #777;
}

/* Buttons */
.btn {
    padding: 10px 18px;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-weight: 600;
}
.btn.primary {
    background: #b7f34a;
}
.btn.ghost {
    background: #f1f1f1;
}
.btn.small {
    margin-top: 10px;
    padding: 8px 14px;
}

/* Services */
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill,minmax(240px,1fr));
    gap: 20px;
    margin-bottom: 40px;
}
.service-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
}
.service-meta {
    display: flex;
    justify-content: space-between;
    margin: 15px 0;
    color: #555;
}
.service-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.status {
    background: #eee;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
}
.status.active {
    background: #d9fbbd;
}

/* Table */
.table-wrapper {
    overflow-x: auto;
}
table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 12px;
}
th, td {
    padding: 14px;
    text-align: left;
    border-bottom: 1px solid #eee;
}
.pill {
    background: #eee;
    padding: 4px 10px;
    border-radius: 20px;
}
.pill.active {
    background: #d9fbbd;
}

/* Package Editor */
.package-editor {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
    margin-top: 40px;
}
.editor-left,
.editor-right {
    background: #fff;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
}
.editor-left input,
.editor-left select {
    width: 100%;
    padding: 10px;
    margin-bottom: 15px;
    border-radius: 8px;
    border: 1px solid #ccc;
}
.offering {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 0;
}

/* Mobile */
@media(max-width: 900px){
    .package-editor {
        grid-template-columns: 1fr;
    }
}

</style>
<div class="height-100">
   <div class="services-page">

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h2>Services & Packages</h2>
            <p>Manage your offerings, pricing and deliverables</p>
        </div>
        <div class="header-actions">
            <button class="btn ghost">+ Add Service</button>
            <button class="btn primary">+ Add Package</button>
        </div>
    </div>

    <!-- SERVICES GRID -->
    <div class="services-grid">

        <div class="service-card active">
            <h3>Branding</h3>
            <p>Logo, identity, visual system</p>
            <div class="service-meta">
                <span>3 Packages</span>
                <span>From PKR 299</span>
            </div>
            <div class="service-footer">
                <span class="status active">Active</span>
                <button class="main-btn">View Packages</button>
            </div>
        </div>

        <div class="service-card">
            <h3>Website Design</h3>
            <p>UI, UX & web builds</p>
            <div class="service-meta">
                <span>4 Packages</span>
                <span>From PKR 499</span>
            </div>
            <div class="service-footer">
                <span class="status">Draft</span>
                <button class="main-btn">View Packages</button>
            </div>
        </div>

    </div>

    <!-- PACKAGES TABLE -->
    <div class="packages-section">
        <h3>Branding → Packages</h3>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Package</th>
                        <th>Price</th>
                        <th>Delivery</th>
                        <th>Offerings</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Basic</td>
                        <td>PKR 299</td>
                        <td>3 Days</td>
                        <td>5 items</td>
                        <td><span class="pill active">Active</span></td>
                        <td>⋮</td>
                    </tr>
                    <tr>
                        <td>Pro</td>
                        <td>PKR 599</td>
                        <td>5 Days</td>
                        <td>10 items</td>
                        <td><span class="pill active">Active</span></td>
                        <td>⋮</td>
                    </tr>
                    <tr>
                        <td>Elite</td>
                        <td>PKR 999</td>
                        <td>10 Days</td>
                        <td>18 items</td>
                        <td><span class="pill">Draft</span></td>
                        <td>⋮</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- PACKAGE EDITOR -->
    <div class="package-editor">

        <div class="editor-left">
            <h3>Pro Package</h3>

            <label>Price</label>
            <input type="text" value="PKR 599">

            <label>Delivery Time</label>
            <input type="text" value="5 Days">

            <label>Status</label>
            <select>
                <option>Active</option>
                <option>Draft</option>
            </select>

            <label class="switch d-flex">
                <input type="checkbox" class="m-2"> 
                <span class="slider" ></span>
                <input type="text" class="m-2" readonly="">Suggested</input>
            </label>
        </div>

        <div class="editor-right">
            <h3>Offerings</h3>

            <div class="offering">
                <input type="checkbox" checked>
                Logo Concepts (3)
            </div>

            <div class="offering">
                <input type="checkbox" checked>
                Color Palette
            </div>

            <div class="offering">
                <input type="checkbox" checked>
                Brand Guidelines
            </div>

            <div class="offering">
                <input type="checkbox">
                Social Media Kit
            </div>

            <button class="btn ghost small">+ Add Offering</button>
        </div>

    </div>

</div>

</div>
<?php
include('dashboard_footer.php');
?>