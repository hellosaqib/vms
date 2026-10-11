<?php
class Auth {
    private $db;
    private $user = null;

    public function __construct($db) {
        $this->db = $db;
        //session_start();
        $this->autoLogin();
    }

    public function register($username, $password, $email) {
        // Clean inputs
        $username = trim($username);
        $password = trim($password);
        $email = trim($email);
        
        // Validate password meets requirements
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }
        
        // Generate hash
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            throw new Exception("Password hashing failed");
        }
        
        // Store user
        $stmt = $this->db->prepare("
            INSERT INTO users (username, password_hash, email) 
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$username, $hash, $email]);
    }
    
    public function login($username, $password) {
        $username = trim($username);
        $password = trim($password);
        //$password = "admin123";
        //$hash = password_hash($password, PASSWORD_DEFAULT);
       // echo $hash;
        //die;
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password_hash'])) {
            $this->user = $user;
            $_SESSION['user_id'] = $user['id'];
            // Fetch role_id of logged-in user
            $user_id = $user['id'];
            $role_id = $user['role_id'];
            $role_name = $user['role_name'];

            // Fetch permissions for this role
            $stmt = $this->db->prepare("
                SELECT p.name 
                FROM role_permissions rp
                JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = ?
            ");
            $stmt->execute([$role_id]);
            $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Store in session
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role_id'] = $role_id;
            $_SESSION['role_name'] = $role_name;
            $_SESSION['permissions'] = $permissions;
            $_SESSION['username'] = $user['username'];
        
            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Set remember me cookie if requested
            if (isset($_POST['remember_me'])) {
                $token = bin2hex(random_bytes(32));
                $expiry = time() + 60 * 60 * 24 * 30; // 30 days
                
                // Set cookie
                setcookie('remember_token', $token, [
                    'expires' => $expiry,
                    'path' => '/',
                    'secure' => true, // Only send over HTTPS
                    'httponly' => true, // Not accessible via JavaScript
                    'samesite' => 'Strict' // Prevent CSRF
                ]);
                
                // Store hashed token in database
                $hashedToken = hash('sha256', $token);
                
                $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $success = $stmt->execute([$hashedToken, $user['id']]);

                if (!$success) {
                    $error = $stmt->errorInfo();
                    echo "Error: " . $error[2]; // This will display the SQL error
                    exit;
                }
                
            }
            
            return true;
        }
        
        return false;
    }


    public function logout() {
        if ($this->isLoggedIn()) {
            // Clear remember token from database
            $stmt = $this->db->prepare("UPDATE users SET remember_token = NULL WHERE id = ?");
            $stmt->execute([$this->user['id']]);
        }
        
        // Clear session and cookies
        unset($_SESSION['user_id']);
        setcookie('remember_token', '', time() - 3600, '/');
        $this->user = null;
    }

    public function changePassword($userId, $newPassword) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = ?, remember_token = NULL 
            WHERE id = ?
        ");
        return $stmt->execute([$hash, $userId]);
    }
    
    private function autoLogin() {
        if (empty($_SESSION['user_id']) && !empty($_COOKIE['remember_token'])) {
            // Hash the token for database comparison
            $hashedToken = hash('sha256', $_COOKIE['remember_token']);
            
            $stmt = $this->db->prepare("
                SELECT * FROM users 
                WHERE remember_token = ? 
                AND is_active = 1
            ");
            $stmt->execute([$hashedToken]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                
                $_SESSION['user_id'] = $user['id'];
                $this->user = $user;
                $role_id = $user['role_id'];
                $role_name = $user['role_name'];

                // Fetch permissions for this role
                $stmt = $this->db->prepare("
                    SELECT p.name 
                    FROM role_permissions rp
                    JOIN permissions p ON rp.permission_id = p.id
                    WHERE rp.role_id = ?
                ");
                $stmt->execute([$role_id]);
                $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Store in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role_id'] = $role_id;
                $_SESSION['role_name'] = $role_name;
                $_SESSION['permissions'] = $permissions;
                $_SESSION['username'] = $user['username'];
                // Update last login
                $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Optionally refresh the remember token (security best practice)
                $this->refreshRememberToken($user['id']);
            }
        } elseif (!empty($_SESSION['user_id'])) {
            $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([$_SESSION['user_id']]);
            $this->user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    private function refreshRememberToken($userId) {
        $newToken = bin2hex(random_bytes(32));
        $expiry = time() + 60 * 60 * 24 * 30; // 30 days
        
        setcookie('remember_token', $newToken, [
            'expires' => $expiry,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        $hashedToken = hash('sha256', $newToken);
        $stmt = $this->db->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $stmt->execute([$hashedToken, $userId]);
    }

    public function isLoggedIn() {
        return $this->user !== null;
    }

    public function getUser() {
        return $this->user;
    }

   public function hasPermission($permissionName) {

    if (!$this->isLoggedIn()) return false;
    
    // Everyone can logout
    if ($permissionName === 'logout') return true;

    // Administrator has all permissions
    if ($this->user['role_id'] == 1) return true;
    
    $stmt = $this->db->prepare("
        SELECT p.name 
        FROM permissions p
        JOIN role_permissions rp ON p.id = rp.permission_id
        WHERE p.name = ?
        AND (
            (rp.role_id = ? AND rp.user_id IS NULL)  -- Role permissions
            OR 
            (rp.user_id = ?)  -- User-specific permissions
        )
    ");
    // You need to pass 3 parameters, not 2
    $stmt->execute([
        $permissionName, 
        $this->user['role_id'], 
        $this->user['id']  // ← This was missing!
    ]);
    
    return $stmt->fetch() !== false;
}

    public function getRoleName() {
        if (!$this->isLoggedIn()) return null;
        
        $stmt = $this->db->prepare("SELECT name FROM roles WHERE id = ?");
        $stmt->execute([$this->user['role_id']]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $role ? $role['name'] : null;
    }
}
?>