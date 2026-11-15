<?php
include('core/config.php');

// Create Table Automatically

$sql = "CREATE TABLE IF NOT EXISTS course_feedback (

    id INT AUTO_INCREMENT PRIMARY KEY,
    student_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    course_name VARCHAR(100) NOT NULL,
    rating INT NOT NULL,
    course_feedback TEXT,
    instructor_feedback TEXT,
    suggestions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

)";


$conn->query($sql);



$message = "";
$error_message = "";

// Form Submission

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $student_name = trim(htmlspecialchars($_POST['student_name'] ?? ''));
    $email = trim(htmlspecialchars($_POST['email'] ?? ''));
    $course_name = trim(htmlspecialchars($_POST['course_name'] ?? ''));
    $rating = intval($_POST['rating'] ?? 5);
    $course_feedback = trim(htmlspecialchars($_POST['course_feedback'] ?? ''));
    $instructor_feedback = trim(htmlspecialchars($_POST['instructor_feedback'] ?? ''));
    $suggestions = trim(htmlspecialchars($_POST['suggestions'] ?? ''));

    // Anti-Spam Security Shield Verification
    $spam_check = gfx_verify_form_submission($conn, 'course_feedback', [
        'student_name'        => $student_name,
        'email'               => $email,
        'course_feedback'     => $course_feedback,
        'instructor_feedback' => $instructor_feedback,
        'suggestions'         => $suggestions
    ], [
        'require_csrf'   => true,
        'require_time'   => true,
        'max_attempts'   => 5,
        'window_seconds' => 600
    ]);

    if (!$spam_check['success']) {
        if ($spam_check['is_bot']) {
            $message = "Thank you! Your feedback has been submitted successfully.";
        } else {
            $error_message = $spam_check['error'];
        }
    } else {
        $stmt = $conn->prepare(
            "INSERT INTO course_feedback 
            (student_name,email,course_name,rating,course_feedback,instructor_feedback,suggestions)
            VALUES (?,?,?,?,?,?,?)"
        );

        $stmt->bind_param(
            "sssisss",
            $student_name,
            $email,
            $course_name,
            $rating,
            $course_feedback,
            $instructor_feedback,
            $suggestions
        );

        if($stmt->execute()){
            $message = "Thank you! Your feedback has been submitted successfully.";
        } else {
            $error_message = "Unable to save feedback. Please try again.";
        }
        $stmt->close();
    }
}
?>


<!DOCTYPE html>
<html>

<head>

<title>Course Feedback</title>


<style>

body{

    font-family:Arial;
    background:#f2f2f2;

}


.container{

    width:500px;
    margin:40px auto;
    background:white;
    padding:25px;
    border-radius:10px;
    box-shadow:0 0 10px #ccc;

}


h2{

    text-align:center;

}


input,
select,
textarea{


    width:100%;
    padding:10px;
    margin:8px 0;
    border:1px solid #ccc;
    border-radius:5px;

}


button{


    width:100%;
    padding:12px;
    background:#024442;
    color:white;
    border:none;
    border-radius:5px;
    cursor:pointer;

}


button:hover{

    background:#012d2c;

}


.success{

    text-align:center;
    color:green;

}


</style>


</head>



<body>


<div class="container">
    <div  style="text-align: center;">
<img src="assets/images/logo.png" width="50%">
</div>
<h2>Course Review / Feedback Form</h2>


<?php

if($message!=""){
    echo "<p class='success'>$message</p>";
}
if(!empty($error_message)){
    echo "<p class='error' style='color:#dc2626;background:#fef2f2;padding:12px;border-radius:8px;'>$error_message</p>";
}
?>

<form method="POST">
<?= gfx_render_anti_spam_fields('course_feedback'); ?>


<label>
Student Name
</label>

<input 
type="text"
name="student_name"
required>



<label>
Email
</label>

<input 
type="email"
name="email">



<label>
Course Name
</label>

<input 
type="text"
name="course_name"
required>




<label>
Course Rating
</label>


<select name="rating" required>

<option value="">Select Rating</option>

<option value="5">
⭐⭐⭐⭐⭐ Excellent
</option>

<option value="4">
⭐⭐⭐⭐ Very Good
</option>

<option value="3">
⭐⭐⭐ Good
</option>

<option value="2">
⭐⭐ Average
</option>

<option value="1">
⭐ Poor
</option>

</select>




<label>
Overall Course Experience
</label>

<textarea 
name="course_feedback"
rows="4"
placeholder="Share your experience">
</textarea>




<label>
Instructor Feedback
</label>

<textarea
name="instructor_feedback"
rows="4"
placeholder="Feedback about instructor">
</textarea>




<label>
Suggestions for Improvement
</label>

<textarea
name="suggestions"
rows="4"
placeholder="Your suggestions">
</textarea>



<button type="submit">
Submit Feedback
</button>



</form>


</div>


</body>

</html>