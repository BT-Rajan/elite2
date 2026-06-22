<?php
/**
 * Run once to create the initial admin user.
 * Usage: php database/seed_admin.php
 */
declare(strict_types=1);

require __DIR__ . '/../config.php';
require __DIR__ . '/../vendor/autoload.php';

use Core\Database;
use Core\Auth;

$db = Database::getInstance();

$email    = 'admin@elite2.com';
$password = 'Admin@1234!';
$first    = 'System';
$last     = 'Admin';

$check = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$check->execute([$email]);
if ($check->fetch()) {
    echo "Admin already exists: $email\n";
    exit;
}

$db->prepare("
    INSERT INTO users (email, password, role, first_name, last_name, is_active)
    VALUES (?, ?, 'admin', ?, ?, 1)
")->execute([$email, Auth::hashPassword($password), $first, $last]);

echo "Admin created.\n";
echo "Email:    $email\n";
echo "Password: $password\n";
echo "CHANGE THIS PASSWORD IMMEDIATELY AFTER FIRST LOGIN.\n";
