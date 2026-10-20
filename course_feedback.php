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


// Form Submission

if ($_SERVER["REQUEST_METHOD"] == "POST") {


    $student_name = $_POST['student_name'];
    $email = $_POST['email'];
    $course_name = $_POST['course_name'];
    $rating = $_POST['rating'];
    $course_feedback = $_POST['course_feedback'];
    $instructor_feedback = $_POST['instructor_feedback'];
    $suggestions = $_POST['suggestions'];



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

    }
    else{

        $message = "Error submitting feedback.";

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

?>


<form method="POST">


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