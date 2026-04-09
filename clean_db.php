<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=nrsmarketing', 'root', '');
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in nrsmarketing:\n";
    print_r($tables);
    
    // Drop all tables
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach($tables as $table) {
        $pdo->exec("DROP TABLE `$table`");
        echo "Dropped table $table\n";
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Done.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
