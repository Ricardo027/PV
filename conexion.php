<?php 
$servername = "localhost";  
$usuario = "root";          
$password = "";             
$dbname = "ventas";        

$conn = new mysqli($servername, $usuario, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

?>
