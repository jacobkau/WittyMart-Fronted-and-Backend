<?php
require_once 'includes/config.php';

$name = 'Admin';
$email = 'admin@wittymart.com';
$password = 'admin123';
$hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Update existing user
        $stmt = $pdo->prepare("UPDATE users SET password = ?, name = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $name, $email]);
        echo "✅ Updated existing admin user\n";
    } else {
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$name, $email, $hashed_password]);
        echo "✅ Created new admin user\n";
    }
    
    echo "Email: $email\n";
    echo "Password: $password\n";
    echo "Hash: $hashed_password\n";
    
    // Verify
    $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $hash = $stmt->fetchColumn();
    
    if (password_verify($password, $hash)) {
        echo "✅ Verification successful! You can now login.\n";
    } else {
        echo "❌ Verification failed!\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
