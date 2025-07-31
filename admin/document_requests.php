<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin access
$auth->requireAdmin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['request_id'])) {
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    $notes = $_POST['notes'] ?? '';
    $adminId = $auth->getCurrentUser()['id'];
    
    try {
        if ($action === 'approve') {
            $status = 'approved';
            $stmt = $db->prepare("UPDATE document_requests SET status = ?, processed_by = ?, processed_at = NOW(), admin_notes = ? WHERE id = ?");
        } else {
            $status = 'rejected';
            $stmt = $db->prepare("UPDATE document_requests SET status = ?, processed_by = ?, processed_at = NOW(), admin_notes = ?, rejection_reason = ? WHERE id = ?");
            $stmt->execute([$status, $adminId, $notes, $_POST['rejection_reason'] ?? '', $requestId]);
        }
        
        // Redirect to prevent form resubmission
        header('Location: document_requests.php?updated=' . $requestId);
        exit;
    } catch (PDOException $e) {
        $error = "Error updating request: " . $e->getMessage();
    }
}

// Get all document requests with user info
$query = "
    SELECT dr.*, u.first_name, u.last_name, u.student_id, dt.name as document_type,
           CONCAT(a.first_name, ' ', a.last_name) as admin_name
    FROM document_requests dr
    JOIN users u ON u.id = dr.user_id
    JOIN document_types dt ON dt.id = dr.document_type_id
    LEFT JOIN users a ON a.id = dr.processed_by
    ORDER BY dr.requested_at DESC
";
$requests = $db->query($query)->fetchAll();

$pageTitle = "Document Requests";
require_once __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Document Requests</h1>
</div>

<?php if (isset($_GET['updated'])): ?>
    <div class="alert alert-success">Request #<?php echo htmlspecialchars($_GET['updated']); ?> has been updated.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead>
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Document Type</th>
                <th>Requested</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?php echo $request['id']; ?></td>
                    <td>
                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                        <br><small class="text-muted"><?php echo htmlspecialchars($request['student_id']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($request['document_type']); ?></td>
                    <td><?php echo date('M j, Y', strtotime($request['requested_at'])); ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo $request['status'] === 'approved' ? 'success' : 
                                 ($request['status'] === 'rejected' ? 'danger' : 'warning'); 
                        ?>">
                            <?php echo ucfirst($request['status']); ?>
                        </span>
                    </td>
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal<?php echo $request['id']; ?>">
                            <i class="bi bi-eye"></i> View
                        </button>
                    </td>
                </tr>

                <!-- Request Details Modal -->
                <div class="modal fade" id="requestModal<?php echo $request['id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Request #<?php echo $request['id']; ?> Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <h6>Student Information</h6>
                                        <p>
                                            <strong>Name:</strong> <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?><br>
                                            <strong>Student ID:</strong> <?php echo htmlspecialchars($request['student_id']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Request Details</h6>
                                        <p>
                                            <strong>Document Type:</strong> <?php echo htmlspecialchars($request['document_type']); ?><br>
                                            <strong>Requested On:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($request['requested_at'])); ?><br>
                                            <strong>Status:</strong> 
                                            <span class="badge bg-<?php 
                                                echo $request['status'] === 'approved' ? 'success' : 
                                                     ($request['status'] === 'rejected' ? 'danger' : 'warning'); 
                                            ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <?php if ($request['status'] === 'pending'): ?>
                                    <hr>
                                    <h5>Process Request</h5>
                                    <form method="post" class="mb-3">
                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Notes (Optional)</label>
                                            <textarea name="notes" class="form-control" rows="2" placeholder="Add any notes for this request"></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Rejection Reason (if rejecting)</label>
                                            <input type="text" name="rejection_reason" class="form-control" placeholder="Reason for rejection">
                                        </div>
                                        
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" name="action" value="approve" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" class="btn btn-danger">
                                                <i class="bi bi-x-circle"></i> Reject
                                            </button>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <strong>Processed by:</strong> <?php echo $request['admin_name'] ? htmlspecialchars($request['admin_name']) : 'System'; ?><br>
                                        <strong>Processed on:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($request['processed_at'])); ?>
                                        <?php if (!empty($request['admin_notes'])): ?>
                                            <br><strong>Notes:</strong> <?php echo htmlspecialchars($request['admin_notes']); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($request['rejection_reason'])): ?>
                                            <br><strong>Rejection Reason:</strong> <?php echo htmlspecialchars($request['rejection_reason']); ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
