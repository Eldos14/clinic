<?php
$hostName = "localhost";      
$database = "polyclinica";  
$dbUser = "polyclinica";        
$dbPassword = "Eldos_2001";            

$conn = mysqli_connect($hostName, $dbUser, $dbPassword, $database);
if (!$conn) {
    die("Ошибка подключения");
}


?>