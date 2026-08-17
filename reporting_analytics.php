<?php
// Enhanced reporting helper with analytics functions
include 'db_connect.php';

function getAverageResolutionTime($building_id = null, $category_id = null) {
    global $conn;
    
    $sql = "SELECT AVG(TIMESTAMPDIFF(HOUR, mr.submitted_at, mr.completed_at)) as avg_hours
            FROM maintenance_requests mr
            WHERE mr.status = 'Completed' AND mr.completed_at IS NOT NULL";
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($building_id) {
        $where[] = "mr.building_id = ?";
        $params[] = $building_id;
        $types .= 'i';
    }
    
    if ($category_id) {
        $where[] = "mr.category_id = ?";
        $params[] = $category_id;
        $types .= 'i';
    }
    
    if ($where) {
        $sql .= ' AND ' . implode(' AND ', $where);
    }
    
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return round($row['avg_hours'] ?? 0, 1);
}

function getCompletionRate($building_id = null, $category_id = null, $start_date = null, $end_date = null) {
    global $conn;
    
    $sql = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
            FROM maintenance_requests mr
            WHERE 1=1";
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($building_id) {
        $where[] = "mr.building_id = ?";
        $params[] = $building_id;
        $types .= 'i';
    }
    
    if ($category_id) {
        $where[] = "mr.category_id = ?";
        $params[] = $category_id;
        $types .= 'i';
    }
    
    if ($start_date) {
        $where[] = "mr.submitted_at >= ?";
        $params[] = $start_date . ' 00:00:00';
        $types .= 's';
    }
    
    if ($end_date) {
        $where[] = "mr.submitted_at <= ?";
        $params[] = $end_date . ' 23:59:59';
        $types .= 's';
    }
    
    if ($where) {
        $sql .= ' AND ' . implode(' AND ', $where);
    }
    
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $total = $row['total'] ?? 0;
    $completed = $row['completed'] ?? 0;
    
    return $total > 0 ? round(($completed / $total) * 100, 1) : 0;
}

function getRequestsByPriority($building_id = null, $start_date = null, $end_date = null) {
    global $conn;
    
    $sql = "SELECT priority, COUNT(*) as count
            FROM maintenance_requests mr
            WHERE 1=1";
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($building_id) {
        $where[] = "mr.building_id = ?";
        $params[] = $building_id;
        $types .= 'i';
    }
    
    if ($start_date) {
        $where[] = "mr.submitted_at >= ?";
        $params[] = $start_date . ' 00:00:00';
        $types .= 's';
    }
    
    if ($end_date) {
        $where[] = "mr.submitted_at <= ?";
        $params[] = $end_date . ' 23:59:59';
        $types .= 's';
    }
    
    if ($where) {
        $sql .= ' AND ' . implode(' AND ', $where);
    }
    
    $sql .= " GROUP BY priority ORDER BY FIELD(priority, 'Urgent', 'High', 'Medium', 'Low')";
    
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

function getTopProblematicBuildings($limit = 10, $start_date = null, $end_date = null) {
    global $conn;
    
    $sql = "SELECT b.building_name, COUNT(*) as total_requests,
            SUM(CASE WHEN mr.status = 'Completed' THEN 1 ELSE 0 END) as completed,
            AVG(TIMESTAMPDIFF(HOUR, mr.submitted_at, mr.completed_at)) as avg_resolution_hours
            FROM maintenance_requests mr
            JOIN buildings b ON mr.building_id = b.building_id
            WHERE 1=1";
    
    $where = [];
    $params = [];
    $types = '';
    
    if ($start_date) {
        $where[] = "mr.submitted_at >= ?";
        $params[] = $start_date . ' 00:00:00';
        $types .= 's';
    }
    
    if ($end_date) {
        $where[] = "mr.submitted_at <= ?";
        $params[] = $end_date . ' 23:59:59';
        $types .= 's';
    }
    
    if ($where) {
        $sql .= ' AND ' . implode(' AND ', $where);
    }
    
    $sql .= " GROUP BY b.building_id ORDER BY total_requests DESC LIMIT ?";
    $params[] = $limit;
    $types .= 'i';
    
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

function getTechnicianWorkload() {
    global $conn;
    
    $sql = "SELECT u.full_name, 
            COUNT(a.assignment_id) as total_assigned,
            SUM(CASE WHEN a.is_current = TRUE THEN 1 ELSE 0 END) as active_assignments,
            SUM(CASE WHEN mr.status = 'Completed' THEN 1 ELSE 0 END) as completed,
            AVG(TIMESTAMPDIFF(HOUR, mr.submitted_at, mr.completed_at)) as avg_resolution_hours
            FROM users u
            LEFT JOIN assignments a ON u.user_id = a.technician_id
            LEFT JOIN maintenance_requests mr ON a.request_id = mr.request_id
            WHERE u.role_id IN (SELECT role_id FROM roles WHERE LOWER(role_name) IN ('technician', 'maintenance officer'))
            GROUP BY u.user_id
            ORDER BY active_assignments DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}

function getSystemwideMetrics($start_date = null, $end_date = null) {
    global $conn;
    
    $sql = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
            SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected,
            AVG(TIMESTAMPDIFF(HOUR, submitted_at, CASE WHEN completed_at IS NOT NULL THEN completed_at ELSE NOW() END)) as avg_hours_open
            FROM maintenance_requests
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    if ($start_date) {
        $sql .= " AND submitted_at >= ?";
        $params[] = $start_date . ' 00:00:00';
        $types .= 's';
    }
    
    if ($end_date) {
        $sql .= " AND submitted_at <= ?";
        $params[] = $end_date . ' 23:59:59';
        $types .= 's';
    }
    
    $stmt = $conn->prepare($sql);
    if ($types && $params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}
?>
