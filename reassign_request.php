<?php
include 'auth.php';
requireRole(['Supervisor']);
include 'send_notification.php';
include 'audit_log.php';
include 'status_history.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    requireCsrf();
    
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $new_technician_id = isset($_POST['technician']) ? intval($_POST['technician']) : 0;
    $due_date = $_POST['due_date'] ?? null;
    $reassignment_reason = $_POST['reason'] ?? 'Supervisor reassignment';
    $supervisor_id = $_SESSION['user_id'];

    if (!$request_id || !$new_technician_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request or technician.']);
        exit;
    }

    // Get current assignment info
    $currentAssignStmt = $conn->prepare(
        "SELECT a.assignment_id, a.technician_id, mr.status 
         FROM assignments a 
         JOIN maintenance_requests mr ON a.request_id = mr.request_id 
         WHERE a.request_id = ? AND a.is_current = TRUE"
    );
    $currentAssignStmt->bind_param("i", $request_id);
    $currentAssignStmt->execute();
    $currentAssignResult = $currentAssignStmt->get_result();
    
    if (!($currentAssignment = $currentAssignResult->fetch_assoc())) {
        echo json_encode(['success' => false, 'message' => 'No active assignment found.']);
        exit;
    }

    $old_technician_id = $currentAssignment['technician_id'];
    $old_status = $currentAssignment['status'];

    // Mark old assignment as no longer current
    $closeStmt = $conn->prepare("UPDATE assignments SET is_current = FALSE WHERE assignment_id = ?");
    $closeStmt->bind_param("i", $currentAssignment['assignment_id']);
    $closeStmt->execute();

    // Create new assignment
    $newAssignStmt = $conn->prepare(
        "INSERT INTO assignments (request_id, technician_id, assigned_by, due_date, is_current) 
         VALUES (?, ?, ?, ?, TRUE)"
    );
    $newAssignStmt->bind_param("iiis", $request_id, $new_technician_id, $supervisor_id, $due_date);
    
    if ($newAssignStmt->execute()) {
        // Record in status history
        $historyRemarks = "Reassigned from technician ID $old_technician_id to ID $new_technician_id. Reason: $reassignment_reason";
        recordStatusHistory($request_id, $old_status, $old_status, $supervisor_id, $historyRemarks);

        // Log to audit trail
        writeAuditLog($supervisor_id, "Reassigned request #$request_id from technician $old_technician_id to $new_technician_id", "assignments", $request_id, $_SERVER['REMOTE_ADDR']);

        // Notify old technician
        sendNotification($old_technician_id, $request_id, "Request #$request_id has been reassigned to another technician.", "Dashboard");

        // Notify new technician
        sendNotification($new_technician_id, $request_id, "You have been assigned request #$request_id (reassigned from another technician).", "Dashboard");

        // Notify requester
        $reqStmt = $conn->prepare("SELECT requester_id FROM maintenance_requests WHERE request_id = ?");
        $reqStmt->bind_param("i", $request_id);
        $reqStmt->execute();
        $reqResult = $reqStmt->get_result();
        if ($reqRow = $reqResult->fetch_assoc()) {
            sendNotification($reqRow['requester_id'], $request_id, "Your request has been reassigned to a different technician.", "Dashboard");
        }

        echo json_encode(['success' => true, 'message' => 'Request reassigned successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error reassigning request: ' . $conn->error]);
    }
    exit;
}

// GET: Load reassignment form
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    
    // Get current request and assignment
    $reqStmt = $conn->prepare(
        "SELECT mr.request_id, mr.title, mr.status, a.technician_id, a.due_date
         FROM maintenance_requests mr
         LEFT JOIN assignments a ON mr.request_id = a.request_id AND a.is_current = TRUE
         WHERE mr.request_id = ?"
    );
    $reqStmt->bind_param("i", $request_id);
    $reqStmt->execute();
    $reqResult = $reqStmt->get_result();
    
    if (!($request = $reqResult->fetch_assoc())) {
        die("Request not found.");
    }

    // Get list of available technicians
    $techStmt = $conn->prepare(
        "SELECT u.user_id, u.full_name, COUNT(a.assignment_id) as active_assignments 
         FROM users u 
         LEFT JOIN assignments a ON u.user_id = a.technician_id AND a.is_current = TRUE 
         LEFT JOIN roles r ON u.role_id = r.role_id 
         WHERE r.role_name IN ('Technician', 'Maintenance Officer') 
         GROUP BY u.user_id 
         ORDER BY u.full_name"
    );
    $techStmt->execute();
    $technicians = $techStmt->get_result();
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Reassign Request - WPU MRS</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <header class="header">
            <div class="container header-top">
                <div class="brand">
                    <h1>WPU Maintenance Request System</h1>
                </div>
                <nav>
                    <a href="dashboard_supervisor.php">Dashboard</a>
                    <a href="logout.php">Logout</a>
                </nav>
            </div>
        </header>

        <main class="main-container">
            <section class="page-banner">
                <h2>Reassign Request</h2>
                <p class="secondary-text">Move this request to another technician.</p>
            </section>

            <div class="dashboard-container">
                <section class="request-details">
                    <h3>Request Details</h3>
                    <p><strong>Request ID:</strong> <?php echo e($request['request_id']); ?></p>
                    <p><strong>Title:</strong> <?php echo e($request['title']); ?></p>
                    <p><strong>Status:</strong> <?php echo e($request['status']); ?></p>
                    <p><strong>Current Due Date:</strong> <?php echo e($request['due_date'] ?? 'Not set'); ?></p>
                </section>

                <section class="reassign-form">
                    <form id="reassignForm">
                        <input type="hidden" name="request_id" value="<?php echo e($request_id); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo e(generateCsrfToken()); ?>">

                        <label for="technician">Assign to Technician *</label>
                        <select id="technician" name="technician" required>
                            <option value="">-- Select Technician --</option>
                            <?php while ($tech = $technicians->fetch_assoc()): ?>
                                <option value="<?php echo e($tech['user_id']); ?>">
                                    <?php echo e($tech['full_name']); ?> 
                                    (<?php echo e($tech['active_assignments']); ?> active)
                                </option>
                            <?php endwhile; ?>
                        </select>

                        <label for="due_date">New Due Date</label>
                        <input type="date" id="due_date" name="due_date" value="<?php echo e($request['due_date'] ?? ''); ?>">

                        <label for="reason">Reason for Reassignment *</label>
                        <textarea id="reason" name="reason" rows="3" placeholder="Explain why this is being reassigned" required></textarea>

                        <button type="submit" class="btn-primary">Reassign Request</button>
                        <a href="dashboard_supervisor.php" class="btn-secondary">Cancel</a>
                    </form>
                </section>
            </div>
        </main>

        <script>
            document.getElementById('reassignForm').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                try {
                    const response = await fetch('reassign_request.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.success) {
                        alert('Request reassigned successfully!');
                        window.location.href = 'dashboard_supervisor.php';
                    } else {
                        alert('Error: ' + result.message);
                    }
                } catch (err) {
                    alert('An error occurred: ' + err.message);
                }
            });
        </script>
    </body>
    </html>
    <?php
}
?>
