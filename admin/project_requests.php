<?php
include('dashboard_header.php');

$requestQuery = "SELECT * FROM project_requests ORDER BY created_at DESC";
$requests = $conn->query($requestQuery);
?>


<div class="height-100">
    <div class="main-card">
        <div class="main-header">
            <h3>📩 Project Requests</h3>
        </div>

        <div class="main-table">
            <div class="table-head">
                <span>Name</span>
                <span>Company</span>
                <span>Email</span>
                <span>Date</span>
                
                <span>Actions</span>
            </div>

            <?php if ($requests && $requests->num_rows > 0): ?>
                <?php while($request = $requests->fetch_assoc()):

                $date = new DateTime($request['created_at']);
                $formattedDate = $date->format('M d, Y H:i');

                $desc = $request['description'];
                $shortDesc = strlen($desc) > 120 ? substr($desc,0,117).'...' : $desc;

                ?>

                <div class="table-row">

                    <div class="client">
                        <div class="avatar"><?= strtoupper($request['name'][0] ?? 'A') ?></div>
                        <div>
                            <strong><?= htmlspecialchars($request['name']) ?></strong>
                            <small><?= htmlspecialchars($request['project_type']) ?></small>
                        </div>
                    </div>

                    <span data-label="Company"><?= htmlspecialchars($request['company']) ?></span>

                    <span data-label="Email"><?= htmlspecialchars($request['email']) ?></span>

                    <span data-label="Date" class="px-5"><?= $formattedDate ?></span>

                    <div data-label="Actions">

                        <button class="btn btn-primary view-details"

                            data-name="<?= htmlspecialchars($request['name']) ?>"
                            data-company="<?= htmlspecialchars($request['company']) ?>"
                            data-email="<?= htmlspecialchars($request['email']) ?>"
                            data-phone="<?= htmlspecialchars($request['phone']) ?>"
                            data-type="<?= htmlspecialchars($request['project_type']) ?>"
                            data-budget="<?= htmlspecialchars($request['budget']) ?>"
                            data-time="<?= htmlspecialchars($request['timeframe']) ?>"
                            data-description="<?= htmlspecialchars($desc) ?>"
                            data-file="<?= htmlspecialchars($request['attachment']) ?>"
                            data-date="<?= $formattedDate ?>"

                        >
                            👁 View
                        </button>

                    </div>

                </div>

                <?php endwhile; ?>

                <?php else: ?>

                <div class="table-row" style="grid-column:1/-1;text-align:center;padding:60px;">
                No requests yet.
                </div>

                <?php endif; ?>

        </div>
    </div>
</div>
<!-- Enhanced Modal -->
<div id="requestModal" class="modal-overlay">

    <div class="modal-content">

        <!-- Header -->
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon">📩</div>
                Project Request Details
            </h4>
            <button class="modal-close" id="closeModal">&times;</button>
        </div>

        <!-- Body -->
        <div class="modal-body">

            <div class="info-grid">

                <!-- Name -->
                <div class="info-row">
                    <div class="info-icon">👤</div>
                    <div class="info-content">
                        <div class="info-label">Name</div>
                        <div class="info-value" id="modalName"></div>
                    </div>
                </div>

                <!-- Company -->
                <div class="info-row">
                    <div class="info-icon">🏢</div>
                    <div class="info-content">
                        <div class="info-label">Company</div>
                        <div class="info-value" id="modalCompany"></div>
                    </div>
                </div>

                <!-- Email -->
                <div class="info-row">
                    <div class="info-icon">✉️</div>
                    <div class="info-content">
                        <div class="info-label">Email</div>
                        <div class="info-value" id="modalEmail"></div>
                    </div>
                </div>

                <!-- Phone -->
                <div class="info-row">
                    <div class="info-icon">📞</div>
                    <div class="info-content">
                        <div class="info-label">Phone</div>
                        <div class="info-value" id="modalPhone"></div>
                    </div>
                </div>

                <!-- Project Type -->
                <div class="info-row">
                    <div class="info-icon">🛠</div>
                    <div class="info-content">
                        <div class="info-label">Project Type</div>
                        <div class="info-value" id="modalType"></div>
                    </div>
                </div>

                <!-- Budget -->
                <div class="info-row">
                    <div class="info-icon">💰</div>
                    <div class="info-content">
                        <div class="info-label">Budget</div>
                        <div class="info-value" id="modalBudget"></div>
                    </div>
                </div>

                <!-- Timeframe -->
                <div class="info-row">
                    <div class="info-icon">⏳</div>
                    <div class="info-content">
                        <div class="info-label">Timeframe</div>
                        <div class="info-value" id="modalTime"></div>
                    </div>
                </div>

                <!-- Date -->
                <div class="info-row">
                    <div class="info-icon">📅</div>
                    <div class="info-content">
                        <div class="info-label">Submitted</div>
                        <div class="info-value" id="modalDate"></div>
                    </div>
                </div>

            </div>

            <!-- Description -->
            <div class="message-box">
                <span class="message-label">Project Description</span>
                <div class="message-content" id="modalDescription"></div>
            </div>

            <!-- Attachment -->
            <div id="modalAttachment" style="margin-top:20px;"></div>

        </div>

        <!-- Footer -->
        <div class="modal-footer">
            <button class="btn btn-secondary" id="closeModalBtn">Close</button>
            <a id="replyLink" class="btn btn-accent">Reply via Email</a>
        </div>

    </div>
</div>

<script>
   const modal = document.getElementById('requestModal');

document.querySelectorAll('.view-details').forEach(btn => {

btn.addEventListener('click', function(){

modal.classList.add('active');
document.body.style.overflow='hidden';

modalName.textContent = this.dataset.name;
modalCompany.textContent = this.dataset.company;
modalEmail.textContent = this.dataset.email;
modalPhone.textContent = this.dataset.phone;
modalType.textContent = this.dataset.type;
modalBudget.textContent = this.dataset.budget || '—';
modalTime.textContent = this.dataset.time;
modalDate.textContent = this.dataset.date;
modalDescription.textContent = this.dataset.description;

replyLink.href = "mailto:"+this.dataset.email;

const file = this.dataset.file;
modalAttachment.innerHTML = file
? `<a href="${file}" target="_blank">📎 View Attachment</a>`
: '';

});

});

closeModal.onclick = closeModalBtn.onclick = () => {
modal.classList.remove('active');
document.body.style.overflow='auto';
};

</script>

<?php
include('dashboard_footer.php');
?>