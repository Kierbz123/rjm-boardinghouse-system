<?php
require __DIR__ . '/../src/autoload.php';
$pdo = App\Database::getConnection();
$user = $pdo->query("SELECT u.id, u.name, u.email, bp.* FROM users u LEFT JOIN boarder_profiles bp ON bp.user_id = u.id WHERE u.id = 12")->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    echo "User 12 not found. Listing all boarders:\n";
    $all = $pdo->query("SELECT u.id, u.name, u.email, bp.status FROM users u LEFT JOIN boarder_profiles bp ON bp.user_id = u.id WHERE u.role = 'boarder'")->fetchAll(PDO::FETCH_ASSOC);
    print_r($all);
} else {
    echo "Found user 12:\n";
    print_r($user);
}
