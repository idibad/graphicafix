<?php
    include('header.php');

    // Fetch all team members
    $query = "SELECT * FROM team ORDER BY created_at ASC";
    $result = $conn->query($query);
?>
    <!-- ABOUT PAGE START -->
    <div class="about-wrapper">

        <!-- Intro Section -->
        <section class="about-hero">
            <div class="container">
                <h1>About Graphicafix</h1>
                <p>We help brands grow through strategic design, clear communication, and a commitment to excellence.</p>
            </div>
        </section>

        <!-- Mission / Vision / Values -->
        <section class="mv-section">
            <div class="container">
                <div class="row g-4">

                    <div class="col-md-4">
                        <div class="mv-card">
                            <h3>Mission</h3>
                            <p>To empower businesses with modern and effective creative solutions.</p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mv-card">
                            <h3>Vision</h3>
                            <p>To become a trusted design partner for brands worldwide.</p>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="mv-card">
                            <h3>Values</h3>
                            <p>Creativity, integrity, precision, and innovation.</p>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Storytelling Section -->
        <section class="story-section">
            <div class="container">
                <div class="row align-items-center">

                    <div class="col-md-6 mb-4 mb-md-0">
                        <img src="images/gfix_bg.png" class="w-100 rounded shadow" alt="Our Story">
                    </div>

                    <div class="col-md-6">
                        <h2>Our Story</h2>
                        <p>Graphicafix began with a simple idea—deliver design that actually works. Over time we expanded into branding, websites, digital marketing, and content creation, serving clients across industries. Our work is built on clarity, creativity, and consistency.</p>
                    </div>

                </div>
            </div>
        </section>

          <!-- Timeline / Journey -->
        <!-- <section class="timeline-section">
            <div class="container">
                <h2 class="text-center mb-5">Our Journey</h2>

                <div class="timeline">

                    <div class="timeline-item">
                        <div class="timeline-content">
                            <span class="year">2022</span>
                            <p>Graphicafix started with a focus on essential design and branding solutions.</p>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-content">
                            <span class="year">2023</span>
                            <p>Expanded into website development, social media, and marketing services.</p>
                        </div>
                    </div>

                    <div class="timeline-item">
                        <div class="timeline-content">
                            <span class="year">2024</span>
                            <p>Served 100+ clients and introduced a complete digital service suite.</p>
                        </div>
                    </div>

                </div>
            </div>
        </section> -->
        <!-- Team Section -->
         <div class="container">
        <div class="team-header pt-5">
            <div class="team-header-small">OUR TEAM</div>
            <h1 class="team-header-title">Meet the <span>Creative Minds</span></h1>
            <p class="team-header-description">
                Talented professionals dedicated to bringing your vision to life with creativity, expertise, and passion.
            </p>
        </div>
        
        <div class="team-cards-cont">
        <div class="row g-4 justify-content-center">
            <div class="row">


            <?php
            while ($member = $result->fetch_assoc()):
                $name = htmlspecialchars($member['member_name']);
                $role = htmlspecialchars($member['role']);
                $bio = "Experienced in " . htmlspecialchars($role); // or you can have a separate 'bio' column
            ?>
                <div class="col-lg-3 col-md-6">
                <div class="member-card">
                    <div class="member-avatar">👩‍🎨</div>
                    <h3 class="member-name"><?= $name ?></h3>
                    <div class="member-role"><?= $role?></div>
                    <p class="member-bio">
                        <?= $bio ?>
                    </p>
                    
                </div>
            </div>
            <?php endwhile; ?>
            </div>


            </div>
            
        </div>
    </div>

    </div>
<?php
    include('footer.php');
?>
