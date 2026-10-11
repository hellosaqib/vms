<?php
class UserManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function createUser($username, $fullname, $email, $password, $roleId, $commission, $is_active) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (username, full_name, email, password_hash, role_id, commission_rate, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$username, $fullname, $email, $passwordHash, $roleId, $commission, $is_active]);
    }
    
    public function updateUser($userId, $data) {
        $updates = [];
        $params = [];
        
        if (isset($data['username'])) {
            $updates[] = "username = ?";
            $params[] = $data['username'];
        }

        if (isset($data['fullname'])) {
            $updates[] = "full_name = ?";
            $params[] = $data['fullname'];
        }
        
        if (isset($data['email'])) {
            $updates[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['password'])) {
            $updates[] = "password_hash = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (isset($data['role_id'])) {
            $updates[] = "role_id = ?";
            $params[] = $data['role_id'];
        }

        if (isset($data['commission_rate'])) {
            $updates[] = "commission_rate = ?";
            $params[] = $data['commission_rate'];
        }
        
        if (isset($data['is_active'])) {
            $updates[] = "is_active = ?";
            $params[] = $data['is_active'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $params[] = $userId;
        
        $query = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        
        return $stmt->execute($params);
    }
    
    public function deleteUser($userId) {
        $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
        return $stmt->execute([$userId]);
    }
    
    public function getUser($userId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function getAllUsers() {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAllRoles() {
        $stmt = $this->db->prepare("SELECT * FROM roles ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllCustomers() {
        $stmt = $this->db->prepare("
            SELECT * 
            FROM customers
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>