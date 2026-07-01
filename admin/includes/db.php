<?php

require_once 'config.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->pdo = getDB();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    // Product methods
    public function getProducts($limit = null) {
        $sql = "SELECT * FROM products ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getProduct($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function addProduct($data) {
        $sql = "INSERT INTO products (name, description, price, image, category_id, stock) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['price'],
            $data['image'],
            $data['category_id'],
            $data['stock']
        ]);
    }
    
    public function updateProduct($id, $data) {
        $sql = "UPDATE products SET name=?, description=?, price=?, image=?, category_id=?, stock=? 
                WHERE id=?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['description'],
            $data['price'],
            $data['image'],
            $data['category_id'],
            $data['stock'],
            $id
        ]);
    }
    
    public function deleteProduct($id) {
        $stmt = $this->pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // Order methods
    public function getOrders() {
        $stmt = $this->pdo->query("SELECT o.*, u.name as customer_name 
                                   FROM orders o 
                                   LEFT JOIN users u ON o.user_id = u.id 
                                   ORDER BY o.created_at DESC");
        return $stmt->fetchAll();
    }
    
    public function getOrder($id) {
        $stmt = $this->pdo->prepare("SELECT o.*, u.name as customer_name 
                                     FROM orders o 
                                     LEFT JOIN users u ON o.user_id = u.id 
                                     WHERE o.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function updateOrderStatus($id, $status) {
        $stmt = $this->pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }
    
    // User methods
    public function authenticateUser($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }
    
    public function getUsers() {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }
    
    // Statistics
    public function getStats() {
        $stats = [];
        
        // Total products
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM products");
        $stats['products'] = $stmt->fetch()['count'];
        
        // Total orders
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM orders");
        $stats['orders'] = $stmt->fetch()['count'];
        
        // Total revenue
        $stmt = $this->pdo->query("SELECT SUM(total) as total FROM orders WHERE status != 'cancelled'");
        $stats['revenue'] = $stmt->fetch()['total'] ?? 0;
        
        // Total customers
        $stmt = $this->pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
        $stats['customers'] = $stmt->fetch()['count'];
        
        return $stats;
    }
}
