<?php
// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Include database connection
include('../../database/db.php');

// Check database connection
if (!$conn) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// Get POST data
$queue_id = isset($_POST['queue_id']) ? intval($_POST['queue_id']) : 0;

// Validate input
if ($queue_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid queue ID'
    ]);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Fetch queue entry with driver details and queue number
    $sql = "SELECT q.id, q.driver_id, q.queue_number, q.status, 
                   d.firstname, d.lastname, d.tricycle_number
            FROM queue q
            LEFT JOIN drivers d ON q.driver_id = d.id
            WHERE q.id = ? AND q.status = 'Onqueue'
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $queue_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => 'Queue entry not found or already dispatched'
        ]);
        exit;
    }
    
    $queueData = $result->fetch_assoc();
    $driver_id = $queueData['driver_id'];
    $queue_number = $queueData['queue_number'];
    $driver_name = trim($queueData['firstname'] . ' ' . $queueData['lastname']);
    $tricycle_number = $queueData['tricycle_number'] ?? 'N/A';
    
    // Update queue status to Removed (instead of Dispatched)
    $updateSql = "UPDATE queue 
                  SET status = 'Removed', dispatch_at = NOW() 
                  WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $queue_id);
    
    if (!$updateStmt->execute()) {
        throw new Exception('Failed to update queue status');
    }
    
    // Insert into history table
    $historySql = "INSERT INTO history (driver_id, driver_name, tricycle_number, dispatch_time, queue_id)
                   VALUES (?, ?, ?, NOW(), ?)";
    $historyStmt = $conn->prepare($historySql);
    $historyStmt->bind_param("issi", $driver_id, $driver_name, $tricycle_number, $queue_id);
    
    if (!$historyStmt->execute()) {
        throw new Exception('Failed to log dispatch history');
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Queue #$queue_number removed successfully",
        'queue_id' => $queue_id,
        'queue_number' => $queue_number,
        'driver_id' => $driver_id,
        'driver_name' => $driver_name,
        'status' => 'Removed'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => 'Error removing driver from queue: ' . $e->getMessage()
    ]);
} finally {
    // Close statements and connection
    if (isset($stmt)) $stmt->close();
    if (isset($updateStmt)) $updateStmt->close();
    if (isset($historyStmt)) $historyStmt->close();
    $conn->close();
}
?>