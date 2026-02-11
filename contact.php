<?php
    include('header.php');

    if (isset($_POST['submit_contact'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);

    // Optional: basic validation
    if (!empty($name) && !empty($email) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO contacts (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);

        if ($stmt->execute()) {
            echo "<script>alert('Message sent successfully!');window.location='contact.php';</script>";
        } else {
            echo "<script>alert('Failed to send message. Please try again.');</script>";
        }

        $stmt->close();
    } else {
        echo "<script>alert('Please fill all fields.');</script>";
    }
}
?>

<section class="contact-header text-center py-5" data-aos="fade-up">
    <div class="container">
        <h1 class="fw-bold mb-3">Get in touch</h1>
        <p class="header-desc">
            We’re here to assist you with inquiries, project discussions, or service details. Send us a message and we’ll respond promptly.
        </p>
    </div>
</section>
<section class="contact-wrapper">
<div class="container">

    <div class="row g-4">

        <!-- Contact Form -->
        <div class="col-lg-6">
            <div class="form-box shadow-sm">
                <h3 class="mb-3 fw-bold">Get in Touch</h3>

                <div id="success-msg" class="msg msg-success">Message sent successfully.</div>
                <div id="error-msg" class="msg msg-error">Please fill all required fields.</div>

                 <!-- Form -->
                <form class="p-4 rounded shadow-sm" style="background-color: #fff;" action="" method="POST">
                    <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Name</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Your Name" required>
                    </div>
                    <div class="mb-3">
                    <label for="email" class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="you@example.com" required>
                    </div>
                    <div class="mb-3">
                    <label for="message" class="form-label fw-semibold">Message</label>
                    <textarea class="form-control" id="message" name="message" rows="5" placeholder="Your Message" required></textarea>
                    </div>
                    <div class="text-center">
                    <button type="submit" name="submit_contact" class="main-btn px-4 py-2 fw-semibold">
                        Send Message
                    </button>
                    </div>
                </form>

            </div>
        </div>

      <!-- Contact Details -->

<div class="col-lg-6 d-flex flex-column">

    <!-- Contact Info Box -->
    <div class="contact-box shadow-sm mb-4 p-4">
        <h3 class="mb-3 fw-bold">Contact Details</h3>

        <p class="mb-2"><strong>Email (General):</strong> contact@graphicafix.com</p>
        <p class="mb-2"><strong>Email (Support):</strong> support@graphicafix.com</p>

        <p class="mb-2"><strong>WhatsApp:</strong> +92 345 4568986</p>
        <p class="mb-2"><strong>Phone:</strong> +92 300 1234567</p>

        <p class="mb-2"><strong>Office:</strong> Office #12, Main Street, Rawalpindi, Pakistan</p>
    </div>

    <!-- Map (Separated Row) -->
    <div class="map-container flex-grow-1">
        <iframe
            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3620.9150667380006!2d67.03046387494794!3d24.83342374540796!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMjTCsDUwJzAwLjMiTiA2N8KwMDEnNTIuMCJF!5e0!3m2!1sen!2s!4v1700000000000"
            loading="lazy"
            allowfullscreen=""
            referrerpolicy="no-referrer-when-downgrade">
        </iframe>
    </div>

</div>
</div>
</div>


</div>
</section>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Client-side validation
document.getElementById("contactForm").addEventListener("submit", function(e) {
    e.preventDefault();

    let name = document.getElementById("name").value.trim();
    let email = document.getElementById("email").value.trim();
    let subject = document.getElementById("subject").value.trim();
    let message = document.getElementById("message").value.trim();

    let success = document.getElementById("success-msg");
    let error = document.getElementById("error-msg");

    success.style.display = "none";
    error.style.display = "none";

    if (!name || !email || !subject || !message) {
        error.style.display = "block";
        return;
    }

    success.style.display = "block";
});
</script>

<?php
    include('footer.php');
?>
