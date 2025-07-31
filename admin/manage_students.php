<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin access
$auth->requireAdmin();

// Get all students with their details
$students = $db->query("
    SELECT u.*, 
           (SELECT COUNT(*) FROM document_requests WHERE user_id = u.id) as request_count,
           (SELECT COUNT(*) FROM grades WHERE student_id = u.id) as grade_count
    FROM users u
    WHERE u.role = 'student' AND u.is_active = 1
    ORDER BY u.last_name, u.first_name
")->fetchAll();

$pageTitle = "Manage Students";
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Manage Students</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="add_student.php" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Add New Student
        </a>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Student ID</th>
                <th>Email</th>
                <th>Document Requests</th>
                <th>Grades</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?php echo $student['id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                    <td><?php echo $student['request_count']; ?></td>
                    <td><?php echo $student['grade_count']; ?></td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="view_student.php?id=<?php echo $student['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-eye"></i> View
                            </a>
                            <a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-secondary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
