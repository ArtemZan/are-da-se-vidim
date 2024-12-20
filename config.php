<?php
$db_config = [
    'host' => 'localhost',
    'dbname' => 'events_db',
    'user' => 'postgres',
    'password' => '1111'
];

try {
    $pdo = new PDO(
        "pgsql:host={$db_config['host']};dbname={$db_config['dbname']}",
        $db_config['user'],
        $db_config['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
} 