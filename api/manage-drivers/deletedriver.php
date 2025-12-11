<?php
    require_once '../../database/db.php';
    
    // Set JSON header
    header('Content-Type: application/json');

    if(isset($_GET['id'])){
        $driver_id = intval($_GET['id']);

        // First, fetch the driver's profile picture path to delete the file
        $selectStmt = $conn->prepare("SELECT profile_pic FROM drivers WHERE id = ?");
        $selectStmt->bind_param("i", $driver_id);
        $selectStmt->execute();
        $result = $selectStmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $profile_pic_path = $row['profile_pic'];
            
            // Delete the driver record
            $deleteStmt = $conn->prepare("DELETE FROM drivers WHERE id = ?");
            $deleteStmt->bind_param("i", $driver_id);
            
            if ($deleteStmt->execute()) {
                // Delete the profile picture file if it exists
                if (!empty($profile_pic_path)) {
                    $full_path = '../../' . $profile_pic_path;
                    if (file_exists($full_path)) {
                        unlink($full_path);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Driver deleted successfully'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to delete driver from database'
                ]);
            }
            
            $deleteStmt->close();
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Driver not found'
            ]);
        }
        
        $selectStmt->close();
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No driver ID provided'
        ]);
    }
    
    $conn->close();
?>