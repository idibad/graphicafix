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

    $logoFolder = 'images/client_logo/';
    $logos = glob($logoFolder . '*.png'); // get all logos


    $projectQuery = "SELECT id, project_name, thumbnail FROM projects ORDER BY created_at DESC";
    $result = $conn->query($projectQuery);
    $counter = 0;
?>

   <div class="hero">
    <div class="container">
        <div class="row">
            <div class="col-12 text-center">

                <h2 class="animated-tagline">
                    <span style="color:#b6f763;">Graphicafix means</span>
                    <span id="changing-word" style="color:#f2f5f7;"></span>
                    <span class="cursor">|</span>
                </h2>

                <p class="header-subtitle">
                    We transform ideas into modern branding, creative visuals, and digital experiences.
                </p>

                <div class="header-buttons">
                    <a href="#" data-bs-toggle="modal" data-bs-target="#projectRequestModal" class="header-btn primary-btn">Request Project</a>
                    <a href="services.php" class="header-btn secondary-btn">Services</a>
                </div>

            </div>
        </div>
    </div>
</div>

<section class="trusted-by">
    <div class="text-center">
        <div class="logo-slider">
            <div class="logo-track">
               <?php
                   // Loop twice
                for ($repeat = 0; $repeat < 2; $repeat++) {
                    foreach ($logos as $index => $logoPath) {
                        echo '<img src="' . htmlspecialchars($logoPath) . '" alt="Client Logo ' . ($index + 1) . '" class="client-logo">';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</section>
          
    <!-- Header ends -->

    <div class="services text-center">
        <div class="container mt-5">
            <div class="section-title color-primary">
                <h1>Creative <span >Solutions</span></h1>
            </div>
            <div class="row cards-cont" data-aos="fade-up">
    <!-- Graphic Design -->
    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fa fa-palette"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Graphic Design</h3>
                <p>We craft visually stunning graphics that bring your brand to life, from logos to marketing materials.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fa fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Web Development -->
    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fa fa-code"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Web Development</h3>
                <p>We build fast, responsive, and SEO-friendly websites that engage your audience and drive growth.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fa fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Writing -->
    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fa fa-pen-nib"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Content Writing</h3>
                <p>We create compelling, SEO-optimized content for blogs, websites, and social media to engage your audience.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fa fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Branding & Identity -->
    <div class="col-lg-3 col-md-6">
        <div class="card-single">
            <div class="card-icon-wrapper">
                <div class="card-icon">
                    <i class="fa fa-lightbulb"></i>
                </div>
            </div>
            <div class="card-info">
                <h3>Branding</h3>
                <p>We develop unique brand identities, including logos, color schemes, and brand guidelines.</p>
                <div class="card-learn-more">
                    <span>Learn More <i class="fa fa-arrow-right"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="action-btn mt-4 mb-5">
            <a href="services.php" class="main-btn p-3">See All Services</a>
        </div>
    </div>
</div>
</div>
</div>
        <!-- Services section ends -->

        <!-- work section start -->
         
        <div class="work-cards">
            <div class="container">        
                <div class="row">
                        <div class="col-md-3 mb-1">
                            <div class="work-card-single">
                                <h4>
                                Creative Portfolio
                                </h4>
                                <p>
                                    Explore some of the creative projects we've crafted for our clients, 
                                    showcasing innovation, quality, and attention to detail.
                                </p>
                                <a href="#" class="main-btn bg-primary-clr" data-bs-toggle="modal" data-bs-target="#projectRequestModal">Request Project</a>
                            </div>
                        </div>
                        <?php
                        while ($project = $result->fetch_assoc()): ?>
                            <div class="col-md-3">
                                <a href="project_details.php?id=<?= intval($project['id']) ?>" class="work-card-link">
                                    <div class="work-card-single">
<img src="admin/<?= htmlspecialchars(!empty($project['thumbnail']) ? $project['thumbnail'] : 'images/placeholder.png') ?>" alt="Project Thumbnail">                                            alt="<?= htmlspecialchars($project['project_name']) ?>">
                                        <div class="overlay">
                                            <h3><?= htmlspecialchars($project['project_name']) ?></h3>
                                        </div>
                                    </div>
                                </a>
                            </div>

                            
                        <?php $counter++; 
                        
                        if ($counter >= 7) break; 
                    endwhile; ?>
                        
                    </div>
                </div>
            </div>
         </div>
            <!-- work ends -->
            <!-- scrolling text -->
            <div class="scroll-text-wrapper">
            <div class="scroll-text">
                <span class="line">
                    <span class="w1">FIX</span>
                    <span class="w2">ENHANCE</span>
                    <span class="w3">AND</span>
                    <span class="w4">ELEVATE</span>
                    <span class="w5">YOUR</span>
                    <span class="w6">BRANDS</span>
                </span>

                <span class="line">
                    <span class="w1">FIX</span>
                    <span class="w2">ENHANCE</span>
                    <span class="w3">AND</span>
                    <span class="w4">ELEVATE</span>
                    <span class="w5">YOUR</span>
                    <span class="w6">BRANDS</span>
                </span>
            </div>
        </div>
<!-- Our process secion  -->

    <section class="process-section">
        <div class="header">
            <div class="header-small">STEP BY STEP PROCESS</div>
            <h1 class="header-title">We Complete every <span>Step Carefully</span></h1>
        </div>

        <div class="flow-container">
            <!-- First Row -->
            <div class="flow-row">
                <div class="step">
                    <div class="step-icon">⚙️</div>
                    <div class="step-title">Planning</div>
                    <div class="step-description">Design the look and feel, content and structure of your custom web or app</div>
                </div>

                <div class="step">
                    <div class="step-icon">🎨</div>
                    <div class="step-title">Design</div>
                    <div class="step-description">Use files and adopt a deep-app group context which works with your task</div>
                </div>

                <div class="step">
                    <div class="step-icon">💻</div>
                    <div class="step-title">Development</div>
                    <div class="step-description">We provide a design mock up from code to bring them to development</div>
                </div>

                <div class="connector connector-top"></div>
            </div>


            <!-- Second Row -->
            <div class="flow-row">
                <div class="step">
                    <div class="step-icon">📋</div>
                    <div class="step-title">Testing</div>
                    <div class="step-description">Using Data that invoke the execution of your design movements</div>
                </div>

                <div class="step">
                    <div class="step-icon">🚀</div>
                    <div class="step-title">Launch</div>
                    <div class="step-description">Organizing them is a core part before of the design movements</div>
                </div>

                <div class="step">
                    <div class="step-icon">🛟</div>
                    <div class="step-title">Support</div>
                    <div class="step-description">Explain and concept of designs helping to deliver the theme</div>
                </div>

                <div class="connector connector-bottom"></div>
            </div>
        </div>
    </section>
        <!-- whychoose us section -->

<section class="why-choose-us">
    <h2>Why Choose Us</h2>
    <div class="container">
    <div class="wcu-grid">
        <div class="wcu-card">
            <i class="fa-regular fa-lightbulb"></i>
            <h3>Creative Approach</h3>
            <p>We combine strategy, creativity, and clean execution to deliver meaningful results.</p>
        </div>

        <div class="wcu-card">
            <i class="fa-regular fa-gem"></i>
            <h3>High Quality</h3>
            <p>Every project is crafted with precision, clarity, and top-tier design standards.</p>
        </div>

        <div class="wcu-card">
            <i class="fa-regular fa-clock"></i>
            <h3>On-Time Delivery</h3>
            <p>We deliver work on time without compromising quality or detail.</p>
        </div>

        <div class="wcu-card">
            <i class="fa-regular fa-handshake"></i>
            <h3>Trusted Partnership</h3>
            <p>We work closely with clients and ensure long-term, reliable collaboration.</p>
        </div>
    </div>
    </div>
</section>

<section class="project-grid">
<?php
// Fetch the 4 projects you want to show (latest 4 for example)
$projectQuery = "SELECT id, project_name, public_description, thumbnail FROM projects ORDER BY created_at LIMIT 4";
$result = $conn->query($projectQuery);

// Predefined layout classes in order
$layoutClasses = ['project-large', 'project-card', 'project-card', 'project-wide'];

$index = 0;
while ($project = $result->fetch_assoc()):
    $projectName = htmlspecialchars($project['project_name']);
    $projectDesc = htmlspecialchars($project['public_description']);
    $projectThumb = htmlspecialchars($project['thumbnail'] ?: 'images/placeholder.png');
    $projectID = intval($project['id']);
    $class = $layoutClasses[$index] ?? 'project-card'; // fallback
?>
    <div class="project-item <?= $class ?>">
        <?php if ($class === 'project-card'): ?>
            <h2 class="project-title"><?= $projectName ?></h2>
            <!-- <p class="project-sub"><?= $projectDesc ?></p> -->
            <a href="project_details.php?id=<?= $projectID ?>" class="project-link dark">View Project ></a>
        <?php else: ?>
            <img src="admin/<?= $projectThumb ?>" alt="<?= $projectName ?>">
            <div class="project-info">
                <h3><?= $projectName ?></h3>
                <!-- <p><?= $projectDesc ?></p> -->
                <a href="project_details.php?id=<?= $projectID ?>" class="project-link">View Project ></a>
            </div>
        <?php endif; ?>
    </div>
<?php
    $index++;
endwhile;
?>
</section>

<!-- testemonial section  -->

<!-- Contact Form Section -->
<section id="contact" class="py-5" style="background-color: #f8f9fa;">
  <div class="container d-flex justify-content-center align-items-center">
    <div class="col-md-8 col-lg-6">
      
      <!-- Heading & Description -->
      <div class="text-center mb-4">
        <h2 class="fw-bold" style="color: #212529;">Get a Free Quote</h2>
        <p class="text-muted" style="font-size: 1rem;">
          Fill out the form below and we’ll provide a personalized quote for your project.
        </p>
      </div>


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




  <!-- Reviews Section -->
<section class="reviews-section">
    <div class="reviews-header">
        <div class="faq-header-small">TESTIMONIALS</div>
        <h1 class="faq-header-title">What Our <span>Clients Say</span></h1>
    </div>

    <?php
    $reviewsQuery  = "SELECT client_name, company, rating, review FROM reviews WHERE visible = 1 ORDER BY created_at DESC";
    $reviewsResult = $conn->query($reviewsQuery);
    $allReviews    = $reviewsResult ? $reviewsResult->fetch_all(MYSQLI_ASSOC) : [];
    $totalReviews  = count($allReviews);
    $initialShow   = 6;
    ?>

    <?php if ($totalReviews > 0): ?>
    <div class="container">
        <div class="row g-4" id="reviewsGrid">
            <?php foreach ($allReviews as $index => $review):
                $stars    = intval($review['rating']);
                $initials = strtoupper(substr($review['client_name'], 0, 1));
                $hidden   = $index >= $initialShow ? 'review-hidden' : '';
            ?>
            <div class="col-12 col-md-6 col-lg-4 review-col <?= $hidden ?>">
                <div class="review-card">
                    <div class="review-stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="review-star <?= $i <= $stars ? 'filled' : 'empty' ?>">★</span>
                        <?php endfor; ?>
                    </div>
                    <p class="review-text">"<?= htmlspecialchars($review['review']) ?>"</p>
                    <div class="review-author">
                        <div class="review-avatar"><?= $initials ?></div>
                        <div class="review-author-info">
                            <div class="review-author-name"><?= htmlspecialchars($review['client_name']) ?></div>
                            <?php if (!empty($review['company'])): ?>
                                <div class="review-author-company"><?= htmlspecialchars($review['company']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Show More / Show Less button — only if more than 6 reviews -->
        <?php if ($totalReviews > $initialShow): ?>
        <div class="reviews-toggle-wrap">
            <button class="reviews-toggle-btn" id="reviewsToggleBtn" onclick="toggleReviews()">
                <span id="reviewsToggleText">Show All <?= $totalReviews ?> Reviews</span>
                <span class="reviews-toggle-icon" id="reviewsToggleIcon">↓</span>
            </button>
        </div>
        <?php endif; ?>

    </div>
    <?php else: ?>
    <div class="container">
        <p class="faq-empty text-center">No reviews available yet.</p>
    </div>
    <?php endif; ?>
</section>

</section>
    <!--FAQs section-->
    <section class="faq-section">
        <div class="faq-header">
            <div class="faq-header-small">FAQ</div>
            <h1 class="faq-header-title">Frequently Asked <span>Questions</span></h1>
        </div>

        <div class="faq-container">
            <?php
            // Fetch all FAQs
            $faqQuery = "SELECT id, question, answer FROM faqs ORDER BY created_at DESC";
            $result = $conn->query($faqQuery);
            if ($result && $result->num_rows > 0): 
            while ($faq = $result->fetch_assoc()):
                $faqQuestion = htmlspecialchars($faq['question']);
                $faqAnswer = htmlspecialchars($faq['answer']);
            ?>
               
                <div class="faq-item">
                <div class="faq-question">
                    <div class="faq-question-text"><?= $faqQuestion ?></div>
                    <div class="faq-icon"></div>
                </div>
                <div class="faq-answer">
                    <div class="faq-answer-text">
                        <?= $faqAnswer ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <?php else: ?>
              <p class="faq-empty">No FAQs available yet.</p>
            <?php endif; ?>
        </div>
    </section>


    <script>
        
let reviewsExpanded = false;

function toggleReviews() {
    const hiddenCols = document.querySelectorAll('.review-col.review-hidden');
    const visibleExtra = document.querySelectorAll('.review-col.review-visible');
    const btn        = document.getElementById('reviewsToggleBtn');
    const btnText    = document.getElementById('reviewsToggleText');
    const btnIcon    = document.getElementById('reviewsToggleIcon');
    const total      = <?= $totalReviews ?>;

    if (!reviewsExpanded) {
        // Show all hidden cards
        hiddenCols.forEach(col => {
            col.classList.remove('review-hidden');
            col.classList.add('review-visible');
        });
        btnText.textContent = 'Show Less';
        btnIcon.classList.add('rotated');
        reviewsExpanded = true;
    } else {
        // Hide cards beyond the initial 6
        visibleExtra.forEach(col => {
            col.classList.remove('review-visible');
            col.classList.add('review-hidden');
        });
        btnText.textContent = 'Show All ' + total + ' Reviews';
        btnIcon.classList.remove('rotated');
        reviewsExpanded = false;

        // Scroll back up to the section smoothly
        document.querySelector('.reviews-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}
    </script>
<?php

    include('footer.php');
?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    // Select all images
    const images = document.querySelectorAll('img');

    images.forEach(img => {
        // If image fails to load, replace with placeholder
        img.addEventListener('error', () => {
            img.src = 'images/placeholder.png';
        });

        // Optional: if src is empty/null, replace immediately
        if (!img.src || img.src.trim() === '') {
            img.src = 'images/placeholder.png';
        }
    });
});
</script>