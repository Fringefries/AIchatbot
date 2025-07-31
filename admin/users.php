<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin access
$auth->requireAdmin();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['user_id'])) {
        $userId = (int)$_POST['user_id'];
        
        try {
            if ($_POST['action'] === 'toggle_status') {
                $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                $stmt->execute([$userId]);
                
                // Redirect to prevent form resubmission
                header('Location: users.php?updated=' . $userId);
                exit;
            } elseif ($_POST['action'] === 'delete' && isset($_POST['confirm_delete'])) {
                // Prevent deleting self
                if ($userId === $auth->getCurrentUser()['id']) {
                    throw new Exception('You cannot delete your own account.');
                }
                
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                
                // Redirect to prevent form resubmission
                header('Location: users.php?deleted=' . $userId);
                exit;
            }
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get all users with their roles
$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM document_requests WHERE user_id = u.id) as request_count
    FROM users u
    ORDER BY u.role, u.first_name, u.last_name
";
$users = $db->query($query)->fetchAll();

$pageTitle = "Manage Users";
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Users</h1>
    <a href="add_user.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add New User
    </a>
</div>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">User #<?php echo htmlspecialchars($_GET['updated']); ?> has been updated.</div>
<?php elseif (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">User has been deleted successfully.</div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Requests</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo $user['id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        <?php if (!empty($user['student_id'])): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($user['student_id']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $user['role'] === 'admin' ? 'danger' : 
                                 ($user['role'] === 'teacher' ? 'info' : 'secondary'); 
                        ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-<?php echo $user['is_active'] ? 'success' : 'secondary'; ?>">
                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td><?php echo $user['request_count']; ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to toggle this user\'s status?');">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <input type="hidden" name="action" value="toggle_status">
                                <button type="submit" class="btn btn-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="bi bi-power"></i>
                                </button>
                            </form>
                            <?php if ($user['id'] !== $auth->getCurrentUser()['id']): ?>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['id']; ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>

                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirm Delete</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to delete this user? This action cannot be undone.</p>
                                <p><strong>User:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                
                                <form method="post" class="mt-3">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmDelete<?php echo $user['id']; ?>" name="confirm_delete" required>
                                        <label class="form-check-label" for="confirmDelete<?php echo $user['id']; ?>">
                                            I understand this action is permanent
                                        </label>
                                    </div>
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Delete User</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
