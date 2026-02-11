<?php

include('dashboard_header.php');

$contactQuery = "SELECT * FROM contacts ORDER BY created_at DESC";
$contacts = $conn->query($contactQuery);
?>

<div class="height-100" style="position: relative;">
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
                    $fullMessage = nl2br(htmlspecialchars($originalMessage));

                    // Shorten message for table (first ~120 chars)
                    $shortMessage = strlen($originalMessage) > 120 
                        ? substr($originalMessage, 0, 117) . '...' 
                        : $originalMessage;
                    $shortMessage = nl2br(htmlspecialchars($shortMessage));
                ?>
                <div class="table-row">
                    <div class="client">
                        <div class="avatar"><?php echo strtoupper($contact['name'][0] ?? 'A'); ?></div>
                        <div>
                            <strong><?php echo htmlspecialchars($contact['name']); ?></strong>
                        </div>
                    </div>

                    <span class="text-muted"><?php echo htmlspecialchars($contact['email']); ?></span>

                    <span><?php echo $shortMessage; ?></span>

                    <span><?php echo $formattedDate; ?></span>

                    <div class="text-end">
                        <button class="btn btn-sm btn-primary view-details"
                                data-name="<?php echo htmlspecialchars($contact['name']); ?>"
                                data-email="<?php echo htmlspecialchars($contact['email']); ?>"
                                data-message="<?php echo $fullMessage; ?>"
                                data-date="<?php echo $formattedDate; ?>">
                            View Details
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="table-row">
                    <span class="text-center text-muted py-4" style="grid-column: 1 / -1;">No contact messages yet.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
<!-- Custom Dashboard Modal -->
<div id="customPopup" class="d-none" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1050;">
    <div class="bg-white rounded-2 shadow-sm" style="width: 95%; max-width: 650px; max-height: 90vh; overflow-y: auto; padding: 2rem; font-family: 'Poppins', sans-serif;">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
            <h4 class="mb-0 fw-semibold">Message Details</h4>
            <button type="button" id="closePopup" class="btn-close"></button>
        </div>

        <!-- Content -->
        <div class="mb-4">
            <div class="mb-2"><strong>From:</strong> <span id="modalName" class="text-primary"></span></div>
            <div class="mb-2"><strong>Email:</strong> <span id="modalEmail" class="text-muted"></span></div>
            <div class="mb-2"><strong>Received:</strong> <span id="modalDate" class="text-muted"></span></div>
            <hr>
            <div id="modalMessage" style="white-space: pre-wrap; line-height: 1.5; color: #333;"></div>
        </div>

        <!-- Actions -->
        <div class="d-flex justify-content-end gap-2">
            <button type="button" id="closePopupBtn" class="btn btn-outline-secondary px-4">Close</button>
            <a href="#" id="replyLink" class="btn btn-primary px-4">Reply via Email</a>
        </div>

    </div>
</div>

<script>
    const popup = document.getElementById('customPopup');
    const closePopup = document.getElementById('closePopup');
    const closePopupBtn = document.getElementById('closePopupBtn');

    // Close modal
    closePopup.addEventListener('click', () => popup.classList.add('d-none'));
    closePopupBtn.addEventListener('click', () => popup.classList.add('d-none'));
    popup.addEventListener('click', (e) => {
        if (e.target === popup) popup.classList.add('d-none'); // click outside
    });

    // Populate modal content
    document.querySelectorAll('.view-details').forEach(button => {
        button.addEventListener('click', function () {
            document.getElementById('modalName').textContent = this.dataset.name;
            document.getElementById('modalEmail').textContent = this.dataset.email;
            document.getElementById('modalDate').textContent = this.dataset.date;
            document.getElementById('modalMessage').textContent = this.dataset.message;
            document.getElementById('replyLink').href = 'mailto:' + this.dataset.email;
            
            popup.classList.remove('d-none');
        });
    });
</script>


<?php
include('dashboard_footer.php');
?>