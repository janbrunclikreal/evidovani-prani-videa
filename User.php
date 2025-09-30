<?php
// Evidence přání videií v3.1.1
// Model pro uživatele

require_once 'database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function authenticate($username, $password) {
        $user = $this->db->fetch(
            "SELECT id, username, password, role FROM users WHERE username = ?",
            [$username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            return $user;
        }
        
        return false;
    }
    
    public function getAllUsers() {
        return $this->db->fetchAll(
            "SELECT id, username, role, created_at FROM users ORDER BY username"
        );
    }
    
    public function createUser($username, $password, $role = 'user') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $this->db->query(
            "INSERT INTO users (username, password, role) VALUES (?, ?, ?)",
            [$username, $hashedPassword, $role]
        );
        
        return $this->db->lastInsertId();
    }
    
    public function updateUser($id, $username, $role) {
        $this->db->query(
            "UPDATE users SET username = ?, role = ?, updated_at = NOW() WHERE id = ?",
            [$username, $role, $id]
        );
        
        return true;
    }
    
    public function changePassword($id, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $this->db->query(
            "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
            [$hashedPassword, $id]
        );
        
        return true;
    }
    
    public function deleteUser($id) {
        $this->db->query("DELETE FROM users WHERE id = ? AND id != 1", [$id]);
        return true;
    }
    
    public function getUserById($id) {
        return $this->db->fetch(
            "SELECT id, username, role FROM users WHERE id = ?",
            [$id]
        );
    }
    
    public function getDashboardStats() {
        $stats = [];
        
        // Celkový počet uživatelů
        $sql = "SELECT COUNT(*) as total_users FROM users";
        $result = $this->db->fetch($sql);
        $stats['total_users'] = $result['total_users'];
        
        // Počet uživatelů podle rolí
        $sql = "SELECT role, COUNT(*) as count FROM users GROUP BY role";
        $result = $this->db->fetchAll($sql);
        $stats['by_role'] = $result;
        
        // Aktivita uživatelů za posledních 30 dní (z audit logu)
        $sql = "SELECT 
                    u.username,
                    COUNT(al.id) as actions_count,
                    MAX(al.created_at) as last_activity
                FROM users u 
                LEFT JOIN audit_log al ON u.id = al.user_id 
                WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY u.id, u.username
                HAVING actions_count > 0
                ORDER BY actions_count DESC
                LIMIT 10";
        $result = $this->db->fetchAll($sql);
        $stats['active_users'] = $result;
        
        // Nově registrovaní uživatelé za posledních 30 dní
        $sql = "SELECT COUNT(*) as new_users FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $this->db->fetch($sql);
        $stats['new_users'] = $result['new_users'];
        
        return $stats;
    }
}
?>