<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=nrsmarketing', 'root', '');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS users, password_reset_tokens, sessions, cache, cache_locks, jobs, job_batches, failed_jobs, migrations');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "Dropped all default tables.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
