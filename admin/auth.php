<?php

require_once 'config.php';
require_once 'db.php';

function login($email, $password) {
    $db = Database::getInstance();
    $user = $db->authenticateUser($email, $password);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        return true;
    }
    return false;
}

function logout() {
    $_SESSION = [];
    session_destroy();
    return true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        redirect('../index.php');
        exit;
    }
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}
