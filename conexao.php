<?php
// config.php (não versionado) define o banco de cada ambiente; sem ele, usa o banco local de desenvolvimento.
$config = is_file(__DIR__ . "/config.php") ? require __DIR__ . "/config.php" : [];

$host = $config["host"] ?? "127.0.0.1";
$user = $config["user"] ?? "root";
$pass = $config["pass"] ?? "";
$database = $config["database"] ?? "registro_voos";

$conn = new mysqli($host, $user, $pass, $database);

if ($conn->connect_error) {
    die("Falha na conexão com o MySQL: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

require_once __DIR__ . "/demo.php";
if (ambienteDemo()) {
    demoRestaurarSeVencido($conn);
}

