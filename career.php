<?php
    include("header.php");
  
$keyword = $_GET['keyword'] ?? '';

$sql = "SELECT * FROM career_positions 
        WHERE position LIKE '%$keyword%' 
        OR department LIKE '%$keyword%' 
        OR location LIKE '%$keyword%'";

$result = mysqli_query($conn, $sql);



if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $position = $_POST['position'];
    $skills = $_POST['skills'];
    $portfolio = $_POST['portfolio'];
    $experience = $_POST['experience'];
    $bio = $_POST['bio'];

    // CV upload
    $cvName = $_FILES['cv']['name'];
    $cvTmp = $_FILES['cv']['tmp_name'];
    $cvPath = "uploads/cvs/" . time() . "_" . $cvName;

    move_uploaded_file($cvTmp, $cvPath);

    $sql = "INSERT INTO career_applications 
            (name, email, phone, position, skills, portfolio, experience, cv, bio)
            VALUES
            ('$name','$email','$phone','$position','$skills','$portfolio','$experience','$cvPath','$bio')";

   $result =  mysqli_query($conn, $sql);
    if($result)
        echo "<script>alert('Application submitted successfully!');</script>";
    else
        echo "<script>alert('Error submitting application. Please try again.');</script>";

}
?>

<style>

     .main-card{
    background:#fff;
    border-radius:16px;
    padding:24px;
    box-shadow:0 10px 30px rgba(0,0,0,.06);
    width:100%;
}

.main-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}

.main-header h3{
    font-size:22px;
    font-weight:700;
}

.main-table{
    width:100%;
    display:flex;
    flex-direction:column;
    gap:12px;
}

.table-head,
.table-row{
    display:grid;
    grid-template-columns: 2fr 1.3fr 1fr .8fr 1fr .5fr;
    align-items:center;
    padding:14px 16px;
}

.table-head{
    color:#777;
    font-size:14px;
    border-bottom:1px solid #eee;
}

.table-row{
    background:#f9fbfc;
    border-radius:12px;
    transition:.25s;
}

.table-row:hover{
    background:#f1f7f3;
    transform:scale(1.01);
}
</style>

<section class="career-header">
  <div class="container">
    <h1>Join Our Team</h1>
    <p>We’re looking for passionate individuals to grow with us. Explore our open positions and find the role that fits you best.</p>
  </div>
</section>

<section class="career-positions py-5" data-aos="zoom-in">
  <div class="container">

  <div class="col-12 col-md-6 col-lg-4 mb-2  mb-md-0 content-end">
        <form method="GET" action="" class="d-flex">
            <input class="form-control" type="text" name="keyword" placeholder="Search">
            <input type="submit" name="search" class="main-btn ms-2" value="Search">
        </form>
    </div>
    <div class="main-table">
      <div class="table-head">
          <span>Position</span>
          <span>Department</span>
          <span>Location</span>
          <span>Type</span>
      </div>

        <?php while($row = mysqli_fetch_assoc($result)) { ?>
            <div class="table-row">
                <span><?= $row['position'] ?></span>
                <span><?= $row['department'] ?></span>
                <span><?= $row['location'] ?></span>
                <span><?= $row['job_type'] ?></span>
            </div>
        <?php } ?>
    </div>



</div>

  </div>
</section>
<!-- Why Work With Us section -->

<section class="why-work-with-us">
  <div class="container">
    <h2 class="text-center pb-5">Why <span class="highlight">Work With Us</span></h2>
    <div class="container">
        <div class="wcu-grid">
          <div class="wcu-card"  data-aos="fade-up">
              <i class="fas fa-lightbulb"></i>
              <h3>Creative Freedom</h3>
              <p>We encourage ideas, experimentation, and original thinking. Your creativity is valued and trusted.</p>
          </div>

          <div class="wcu-card" data-aos="fade-up">
              <i class="fas fa-rocket"></i>
              <h3>Growth Opportunities</h3>
              <p>Learn, evolve, and build your career with real projects that push your skills forward.</p>
          </div>

          <div class="wcu-card" data-aos="fade-up">
              <i class="fas fa-users"></i>
              <h3>Supportive Team</h3>
              <p>Work alongside designers, developers, and strategists who respect collaboration and teamwork.</p>
          </div>

          <div class="wcu-card" data-aos="fade-up">
              <i class="fas fa-briefcase"></i>
              <h3>Meaningful Work</h3>
              <p>Contribute to real brands and businesses, and see the impact of your work in the real world.</p>
          </div>

        </div>
    </div>
    </div>
</section>


<!-- Application Form -->
<section class="career-form py-5">
  <div class="container">
    <div class="career-form-cont" data-aos="fade-up">
    <h2 class="text-center mb-3">Submit Your Application</h2>
    <p class="text-center text-muted mb-4">Fill in your details and we’ll get back to you soon.</p>

    <form method="POST" action="" enctype="multipart/form-data">
      <div class="row g-4">

        <div class="col-lg-4 col-md-6">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>

        <div class="col-lg-4 col-md-6">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" required>
        </div>

        <div class="col-lg-4 col-md-6">
          <label class="form-label">Phone Number</label>
          <input type="text" name="phone" class="form-control" required>
        </div>

        <div class="col-lg-4 col-md-6">
          <label class="form-label">Position Applying For</label>
          <select name="position" class="form-select" required>
            <option value="" disabled selected>Select Position</option>
            <option>Graphic Designer</option>
            <option>Video Editor</option>
            <option>Web Developer</option>
            <option>Content Writer</option>
            <option>Social Media Manager</option>
            <option>Internship</option>
          </select>
        </div>

        <div class="col-lg-8 col-md-6">
          <label class="form-label">Skills</label>
          <input type="text" name="skills" class="form-control" placeholder="Photoshop, Illustrator">
        </div>

        <div class="col-lg-4 col-md-6">
          <label class="form-label">Portfolio Link</label>
          <input type="url" name="portfolio" class="form-control" placeholder="https://">
        </div>

        <div class="col-lg-4 col-md-6">
          <label class="form-label">Experience Level</label>
          <select name="experience" class="form-select">
            <option selected disabled>Select</option>
            <option>Beginner</option>
            <option>Intermediate</option>
            <option>Expert</option>
          </select>
        </div>

        <div class="col-lg-4 col-md-6">
          <label class="form-label">Upload CV</label>
          <input type="file" name="cv" class="form-control" required>
        </div>

        <div class="col-12">
          <label class="form-label">Short Bio / Introduction</label>
          <textarea name="bio" class="form-control" rows="4" placeholder="Tell us about yourself"></textarea>
        </div>

        <div class="col-12 text-center mt-4">
          <button class="main-btn px-5 py-2" type="submit">Submit Application</button>
        </div>

      </div>
    </form>
  </div>
  </div>
</section>

<?php
    include("footer.php");
?>