<?php
include('dashboard_header.php');

$contactQuery = "SELECT * FROM contacts ORDER BY created_at DESC";
$contacts = $conn->query($contactQuery);
?>


<div class="height-100">
    <div class="main-card">
        <div class="main-header">
            <h3>📩 Contact Messages</h3>
        </div>

        <div class="main-table">
            <div class="table-head">
                <span>Name</span>
                <span>Email</span>
                <span>Message</span>
                <span>Date</span>
                <span>Actions</span>
            </div>

            <?php if ($contacts && $contacts->num_rows > 0): ?>
                <?php while($contact = $contacts->fetch_assoc()): 
                    $date = new DateTime($contact['created_at']);
                    $formattedDate = $date->format('M d, Y H:i');

                    $originalMessage = $contact['message'];
                    $fullMessage = htmlspecialchars($originalMessage);

                    $shortMessage = strlen($originalMessage) > 120 
                        ? substr($originalMessage, 0, 117) . '...' 
                        : $originalMessage;
                    $shortMessage = htmlspecialchars($shortMessage);
                ?>
                <div class="table-row">
                    <div class="client">
                        <div class="avatar"><?php echo strtoupper($contact['name'][0] ?? 'A'); ?></div>
                        <div>
                            <strong><?php echo htmlspecialchars($contact['name']); ?></strong>
                        </div>
                    </div>

                    <span class="text-muted" data-label="Email"><?php echo htmlspecialchars($contact['email']); ?></span>

                    <span data-label="Message"><?php echo $shortMessage; ?></span>

                    <span data-label="Date"><?php echo $formattedDate; ?></span>

                    <div data-label="Actions">
                        <button class="btn btn-primary view-details"
                                data-name="<?php echo htmlspecialchars($contact['name']); ?>"
                                data-email="<?php echo htmlspecialchars($contact['email']); ?>"
                                data-message="<?php echo $fullMessage; ?>"
                                data-date="<?php echo $formattedDate; ?>">
                            👁️ View
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-row" style="grid-column: 1 / -1; text-align: center; padding: 60px 24px; color: var(--gray-500);">
                    <span>No contact messages yet.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Enhanced Modal -->
<div id="contactModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h4>
                <div class="modal-header-icon">📩</div>
                <span>Message Details</span>
            </h4>
            <button class="modal-close" id="closeModal">&times;</button>
        </div>

        <div class="modal-body">
            <div class="info-grid">
                <div class="info-row">
                    <div class="info-icon">👤</div>
                    <div class="info-content">
                        <div class="info-label">From</div>
                        <div class="info-value" id="modalName"></div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">📧</div>
                    <div class="info-content">
                        <div class="info-label">Email Address</div>
                        <div class="info-value" id="modalEmail"></div>
                    </div>
                </div>

                <div class="info-row">
                    <div class="info-icon">📅</div>
                    <div class="info-content">
                        <div class="info-label">Received</div>
                        <div class="info-value" id="modalDate"></div>
                    </div>
                </div>
            </div>

            <div class="message-box">
                <span class="message-label">💬 Message</span>
                <div class="message-content" id="modalMessage"></div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" id="closeModalBtn">Close</button>
            <a href="#" id="replyLink" class="btn btn-accent">
                ✉️ Reply via Email
            </a>
        </div>
    </div>
</div>

<script>
    const modal = document.getElementById('contactModal');
    const closeModal = document.getElementById('closeModal');
    const closeModalBtn = document.getElementById('closeModalBtn');

    // Open modal
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function () {
            document.getElementById('modalName').textContent = this.dataset.name;
            document.getElementById('modalEmail').textContent = this.dataset.email;
            document.getElementById('modalDate').textContent = this.dataset.date;
            document.getElementById('modalMessage').textContent = this.dataset.message;
            document.getElementById('replyLink').href = 'mailto:' + this.dataset.email;
            
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal
    function closeContactModal() {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }

    closeModal.addEventListener('click', closeContactModal);
    closeModalBtn.addEventListener('click', closeContactModal);

    // Close on backdrop click
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeContactModal();
    });

    // Close on ESC key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('active')) {
            closeContactModal();
        }
    });
</script>

<?php
include('dashboard_footer.php');
?>