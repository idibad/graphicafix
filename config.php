
<link href="css/bootstrap.css" rel="stylesheet">
<link rel="icon" type="image/png" href="images/icon.png">
<?php
    $server = "localhost";
    $username = "root";
    $password = "";
    $db = "graphica_fix_db";


    $conn = @mysqli_connect($server, $username,$password, $db);

    if(!$conn){

       echo "<div class='alert alert-danger alert-dismissible fade show' role='alert'>
  Connection unsuccesful
  <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
</div>";
    }    

?>