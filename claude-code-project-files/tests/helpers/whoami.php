<?php
// Prints which MariaDB login this process ended up with (used by tests/test_wp01_credentials.php).
require __DIR__ . '/../../src/autoload.php';
$pdo = App\Database::getConnection();
echo json_encode(['who' => $pdo->query('SELECT CURRENT_USER()')->fetchColumn()] + App\Database::describe());
