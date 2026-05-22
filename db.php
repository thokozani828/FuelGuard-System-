<?php

$host = "db.shdaldiqnbtlgjajxroi.supabase.co";
$port = "5432";
$dbname = "postgres";
$user = "postgres";
$password = "RangerFord828@2";

try {
    $pdo = new PDO(
        "pgsql:host=$host;port=$port;dbname=$dbname",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Connected successfully";

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

?>