<?php
require_once '../../database/db.php';

header('Content-Type: application/json');

if (isset($_GET['id'])) {
    $driver_id = intval($_GET['id']);
    
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ?");
    $stmt->bind_param("i", $driver_id);
    
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $driver = $result->fetch_assoc();
            echo json_encode([
                'success' => true,
                'data' => $driver
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Driver not found'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error'
        ]);
    }
    
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No ID provided'
    ]);
}

$conn->close();
?>