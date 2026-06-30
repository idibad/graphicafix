 <!-- Page content-->
 <div class="container-fluid">
    <div class="main-cards">
        <div class="card">
            <h4>Your Information</h4>
            <p>Name: <?php echo "$name"?></p>
            <p>Phone: <?php echo "$phone"?></p>
            <p>Email: <?php echo "$email"?></p>
           
        </div>
        <div class="card">
            <h4>Important Notics</h4>
        </div>


        <style>
        .main-cards{
            width: 90%;
            display: grid;
            grid-template-columns: repeat(2,50%);
            gap: 10px;

            
        }
        .card{
            width: 100%;
            height: auto;
            background: #fff;
            margin: 10px;
            border-radius: 20px;
            box-shadow: 0 5px 10px #ccc;
            padding: 20px;



        }
        </style>
</div>
</div>