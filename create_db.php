<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    $pdo->exec('DROP DATABASE IF EXISTS nrsmarketing');
    echo "Dropped database completely.\n";
    $pdo->exec('CREATE DATABASE nrsmarketing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "Recreated database successfully.\n";
    $pdo->exec('USE nrsmarketing');
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in nrsmarketing: " . count($tables) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
