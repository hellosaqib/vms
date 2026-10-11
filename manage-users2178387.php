<?php
$requiredPermission = 'manage_users';
require_once 'includes/check_auth.php';

include 'header.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_user'])) {
        $username = $_POST['username'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $roleId = $_POST['role_id'];
        
        if ($userManager->createUser($username, $email, $password, $roleId)) {
            $success = 'User created successfully';
        } else {
            $error = 'Failed to create user';
        }
    } elseif (isset($_POST['update_user'])) {
        $userId = $_POST['user_id'];
        $data = [
            'username' => $_POST['username'],
            'email' => $_POST['email'],
            'role_id' => $_POST['role_id'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        
        if ($userManager->updateUser($userId, $data)) {
            $success = 'User updated successfully';
        } else {
            $error = 'Failed to update user';
        }
    } elseif (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'];
        
        if ($userManager->deleteUser($userId)) {
            $success = 'User deleted successfully';
        } else {
            $error = 'Failed to delete user';
        }
    }
}

$users = $userManager->getAllUsers();
$roles = $userManager->getAllRoles();
?>

<div class="user-management">
    <h2>User Management</h2>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="user-list">
        <h3>All Users</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= $user['id'] ?></td>
                        <td><?= htmlspecialchars($user['username']) ?></td>
                        <td><?= htmlspecialchars($user['email']) ?></td>
                        <td><?= htmlspecialchars($user['role_name']) ?></td>
                        <td><?= $user['is_active'] ? 'Active' : 'Inactive' ?></td>
                        <td>
                            <button class="btn btn-sm btn-primary edit-user" data-user-id="<?= $user['id'] ?>">Edit</button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <button type="submit" name="delete_user" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="user-form">
        <h3>Add New User</h3>
        <form method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="role_id">Role</label>
                <select id="role_id" name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
        </form>
    </div>
    
    <!-- Edit User Modal (shown when edit button is clicked) -->
    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit User</h3>
            <form method="post" id="editUserForm">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_username">Username</label>
                    <input type="text" id="edit_username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_password">New Password (leave blank to keep current)</label>
                    <input type="password" id="edit_password" name="password">
                </div>
                
                <div class="form-group">
                    <label for="edit_role_id">Role</label>
                    <select id="edit_role_id" name="role_id" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" id="edit_is_active" name="is_active" value="1"> Active
                    </label>
                </div>
                
                <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript to handle the edit modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editUserModal');
    const closeBtn = document.querySelector('.close');
    const editButtons = document.querySelectorAll('.edit-user');
    
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-user-id');
            
            // Fetch user data (in a real app, you'd use AJAX)
            // For demo, we'll use the data from the table row
            const row = this.closest('tr');
            const username = row.cells[1].textContent;
            const email = row.cells[2].textContent;
            const role = row.cells[3].textContent;
            const isActive = row.cells[4].textContent === 'Active';
            
            // Find role ID
            let roleId;
            const roleSelect = document.getElementById('edit_role_id');
            for (let i = 0; i < roleSelect.options.length; i++) {
                if (roleSelect.options[i].text === role) {
                    roleId = roleSelect.options[i].value;
                    break;
                }
            }
            
            // Populate form
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role_id').value = roleId;
            document.getElementById('edit_is_active').checked = isActive;
            
            // Show modal
            modal.style.display = 'block';
        });
    });
    
    closeBtn.addEventListener('click', function() {
        modal.style.display = 'none';
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
});
</script>

<?php include 'footer.php'; ?>