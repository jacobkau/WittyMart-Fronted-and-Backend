<?php

require_once 'db.php';

function getFeaturedProducts($limit = 8) {
    $db = Database::getInstance();
    return $db->getProducts($limit);
}

function getProduct($id) {
    $db = Database::getInstance();
    return $db->getProduct($id);
}

function formatPrice($price) {
    return 'Ksh ' . number_format($price, 2);
}

function getCategories() {
    $db = Database::getInstance();
    $stmt = $db->getPDO()->query("SELECT * FROM categories ORDER BY name");
    return $stmt->fetchAll();
}

function getOrders() {
    $db = Database::getInstance();
    return $db->getOrders();
}

function getOrder($id) {
    $db = Database::getInstance();
    return $db->getOrder($id);
}

function updateOrderStatus($id, $status) {
    $db = Database::getInstance();
    return $db->updateOrderStatus($id, $status);
}

function getUsers() {
    $db = Database::getInstance();
    return $db->getUsers();
}

function getStats() {
    $db = Database::getInstance();
    return $db->getStats();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function redirect($url) {
    header("Location: " . $url);
    exit;
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateSlug($string) {
    $string = strtolower($string);
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', '-', $string);
    return trim($string, '-');
}

function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'processing' => 'badge-info',
        'shipped' => 'badge-primary',
        'delivered' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    return $badges[$status] ?? 'badge-secondary';
}
