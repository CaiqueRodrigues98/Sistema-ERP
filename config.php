<?php
// config.php - Conexão com o banco de dados MySQL

$host = 'localhost';
$db   = 'mini_erp';
$user = 'root';
$pass = 'root';    

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Erro na conexão: ' . $e->getMessage());
}
?>
