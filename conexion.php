<?php
$host = 'localhost';
$port = '5432';
$dbname = 'repuestos_db';
$user = 'postgres';
$password = '2203';

try{
    $conexion = new PDO("pgsql:host=$host;port=$port;dbname=$dbname",$user,$password);

    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}catch (PDOException $e){
    echo "Error de conexion". $e->getMessage();
}
?>