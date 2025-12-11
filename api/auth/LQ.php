<?php
include('../../database/db.php');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ["success" => false, "message" => "Invalid action"];

switch ($action) {
    // ---------------- ADD DRIVER FROM FACE RECOGNITION ----------------
    case "add_queue":
        $driver_id = intval($_POST['driver_id'] ?? 0);

        if ($driver_id > 0) {
            // Check if driver already in queue today
            $checkStmt = $conn->prepare("SELECT id FROM queue WHERE driver_id = ? AND status = 'Onqueue' AND DATE(queued_at) = CURDATE()");
            $checkStmt->bind_param("i", $driver_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $response = ["success" => false, "message" => "Driver already in queue today"];
            } else {
                // Add driver to queue
                $stmt = $conn->prepare("INSERT INTO queue (driver_id, queued_at, status) VALUES (?, NOW(), 'Onqueue')");
                $stmt->bind_param("i", $driver_id);

                if ($stmt->execute()) {
                    $response = ["success" => true, "message" => "Driver added to queue successfully"];
                } else {
                    $response = ["success" => false, "message" => "Database error: " . $conn->error];
                }
                $stmt->close();
            }
            $checkStmt->close();
        } else {
            $response = ["success" => false, "message" => "Invalid driver ID"];
        }
        echo json_encode($response);
        exit;

    // ---------------- DISPATCH DRIVER (UPDATE STATUS) ----------------
    case "dispatch":
        $driver_id = intval($_POST['driver_id'] ?? 0);

        if ($driver_id > 0) {
            // Update driver status to 'Dispatched' instead of deleting
            $stmt = $conn->prepare("
                UPDATE queue 
                SET status = 'Dispatched'
                WHERE driver_id = ? 
                AND status = 'Onqueue' 
                AND DATE(queued_at) = CURDATE()
                ORDER BY queued_at ASC 
                LIMIT 1
            ");
            $stmt->bind_param("i", $driver_id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $response = ["success" => true, "message" => "Driver marked as dispatched"];
            } else {
                $response = ["success" => false, "message" => "Driver not found in queue or already dispatched"];
            }
            $stmt->close();
        } else {
            $response = ["success" => false, "message" => "Invalid driver ID"];
        }
        echo json_encode($response);
        exit;

    // ---------------- ADD DRIVER (MANUAL) ----------------
    case "add":
        $driver = trim($_POST['driver_name'] ?? '');
        $tricycle = trim($_POST['tricycle_no'] ?? '');
        $contact = trim($_POST['contact_no'] ?? '');

        if (!empty($driver) && !empty($tricycle)) {
            $stmt = $conn->prepare("INSERT INTO queue (driver_name, tricycle_no, contact_no, status, created_at) VALUES (?, ?, ?, 'Onqueue', NOW())");
            $stmt->bind_param("sss", $driver, $tricycle, $contact);

            if ($stmt->execute()) {
                $response = ["success" => true, "message" => "Driver added to queue"];
            } else {
                $response = ["success" => false, "message" => "Database error: failed to add driver"];
            }
            $stmt->close();
        } else {
            $response = ["success" => false, "message" => "Driver name and tricycle number are required"];
        }
        echo json_encode($response);
        exit;

    // ---------------- SERVE DRIVER ----------------
    case "serve":
        $id = intval($_POST['id'] ?? 0);

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE queue SET status='SERVING' WHERE id=?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $response = ["success" => true, "message" => "Driver is now serving"];
            } else {
                $response = ["success" => false, "message" => "Failed to update driver status"];
            }
            $stmt->close();
        } else {
            $response = ["success" => false, "message" => "Invalid driver ID"];
        }
        echo json_encode($response);
        exit;

    // ---------------- MARK DONE (DELETE FROM QUEUE) ----------------
    case "done":
        $id = intval($_POST['id'] ?? 0);

        if ($id > 0) {
            // DELETE the driver from queue instead of updating status
            $stmt = $conn->prepare("DELETE FROM queue WHERE id=?");
            $stmt->bind_param("i", $id);

            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $response = ["success" => true, "message" => "Driver completed and removed from queue"];
            } else {
                $response = ["success" => false, "message" => "Failed to remove driver"];
            }
            $stmt->close();
        } else {
            $response = ["success" => false, "message" => "Invalid driver ID"];
        }
        echo json_encode($response);
        exit;

    // ---------------- FETCH QUEUE ----------------
    case "fetch":
        // Fetch ALL Onqueue drivers for today, ordered by queue time
        $query = "SELECT q.*, 
                  d.firstname, 
                  d.lastname, 
                  d.tricycle_number, 
                  d.contact_no,
                  d.profile_pic,
                  CONCAT(d.firstname, ' ', d.lastname) as driver_name
                  FROM queue q
                  LEFT JOIN drivers d ON q.driver_id = d.id
                  WHERE q.status = 'Onqueue'
                  AND DATE(q.queued_at) = CURDATE()
                  ORDER BY q.queued_at ASC";
        
        $result = $conn->query($query);

        if ($result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            
            // Separate first driver (serving) from the rest (waiting)
            $servingDriver = !empty($rows) ? $rows[0] : null;
            $waitingDrivers = !empty($rows) ? array_slice($rows, 1) : [];
            
            $response = [
                "success" => true, 
                "serving" => $servingDriver,
                "waiting" => $waitingDrivers,
                "data" => $rows  // Keep original format for compatibility
            ];
        } else {
            $response = ["success" => false, "message" => "Failed to fetch queue"];
        }
        echo json_encode($response);
        exit;

    default:
        echo json_encode($response);
        exit;
}
?>
