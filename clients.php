<?php
    require_once('config.php');
    include('dashboard_header.php');
    $clients_query = "SELECT * FROM clients ORDER BY client_id";
    $clients_result = mysqli_query($conn, $clients_query);
?>
        <!-- Page content-->
                <div class="height-100">
           
               <div class="main-table">
                    <table>
                        <thead>
                            <tr>
                                <td>ID</td>
                                <td>Client</td>
                                <td>Phone</td>
                                <td>Email</td>
                            </tr>
                        </thead>

                        <tbody>
                          <?php
                          
                            while($data = mysqli_fetch_assoc($clients_result)){

                                $client_id = $data['client_id'];
                                $client = $data['name'];
                                $phone = $data['phone'];
                                $email = $data['email'];


                                echo "<tr>
                                        <td>$client_id</td>
                                        <td>$client</td>
                                        <td>$email</td>
                                        <td>$phone</td>
                                    </tr>";

                            }
                          
                          ?>

                                   

                        </tbody>
                    </table>
                </div>
        </div>
    </div>

<?php include('dashboard_footer.php');?>