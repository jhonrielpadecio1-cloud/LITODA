<?php
// config.php - Place in your heliohost root directory
date_default_timezone_set('Asia/Manila');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Change to your Render domain for security
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(0);
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'jhonrielpadecio0_litoda_user');
define('DB_PASS', 'LitodaDB#2025!');
define('DB_NAME', 'jhonrielpadecio0_litoda_db');

function getDBConnection() {
    try {
        // Try connecting WITH database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $conn->set_charset("utf8mb4");
        $conn->query("SET time_zone = '+08:00'");
        return $conn;
    } catch (mysqli_sql_exception $e) {
        // Error 1049 = Unknown database
        if ($e->getCode() == 1049) {
            // Connect WITHOUT selecting database
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
            
            // Create database
            $conn->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`
                          CHARACTER SET utf8mb4
                          COLLATE utf8mb4_unicode_ci");
            // Select database
            $conn->select_db(DB_NAME);
            // Set charset & timezone
            $conn->set_charset("utf8mb4");
            $conn->query("SET time_zone = '+08:00'");
            return $conn;
        } else {
            // Other errors
            http_response_code(500);
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
}
?>

<?php
// api/get_driver.php - Get driver information by ID
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$driver_id = $_GET['driver_id'] ?? null;

if (!$driver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'driver_id required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT * FROM drivers WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $driver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Driver not found']);
    } else {
        $driver = $result->fetch_assoc();
        echo json_encode(['success' => true, 'driver' => $driver]);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
// api/get_all_drivers.php - Get all drivers with profile pictures
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $sql = "SELECT id, firstname, lastname, tricycle_number, contact_no, profile_pic 
            FROM drivers 
            WHERE profile_pic IS NOT NULL AND profile_pic != ''
            ORDER BY lastname, firstname";
    
    $result = $conn->query($sql);
    
    $drivers = [];
    while ($row = $result->fetch_assoc()) {
        $drivers[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'drivers' => $drivers, 
        'count' => count($drivers),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
// api/register_driver.php - Register new driver
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$firstname = $data['firstname'] ?? '';
$lastname = $data['lastname'] ?? '';
$tricycle_number = $data['tricycle_number'] ?? '';
$contact_no = $data['contact_no'] ?? '';
$profile_pic = $data['profile_pic'] ?? '';

if (empty($firstname) || empty($lastname)) {
    http_response_code(400);
    echo json_encode(['error' => 'First name and last name required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare(
        "INSERT INTO drivers (firstname, lastname, tricycle_number, contact_no, profile_pic, created_at) 
         VALUES (?, ?, ?, ?, ?, NOW())"
    );
    $stmt->bind_param("sssss", $firstname, $lastname, $tricycle_number, $contact_no, $profile_pic);
    
    if ($stmt->execute()) {
        $driver_id = $conn->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Driver registered successfully',
            'driver_id' => $driver_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to register driver']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
// api/update_driver.php - Update driver information
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$driver_id = $data['driver_id'] ?? null;

if (!$driver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'driver_id required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    // Build update query dynamically
    $updates = [];
    $types = "";
    $params = [];
    
    if (isset($data['firstname'])) {
        $updates[] = "firstname = ?";
        $types .= "s";
        $params[] = $data['firstname'];
    }
    
    if (isset($data['lastname'])) {
        $updates[] = "lastname = ?";
        $types .= "s";
        $params[] = $data['lastname'];
    }
    
    if (isset($data['tricycle_number'])) {
        $updates[] = "tricycle_number = ?";
        $types .= "s";
        $params[] = $data['tricycle_number'];
    }
    
    if (isset($data['contact_no'])) {
        $updates[] = "contact_no = ?";
        $types .= "s";
        $params[] = $data['contact_no'];
    }
    
    if (isset($data['profile_pic'])) {
        $updates[] = "profile_pic = ?";
        $types .= "s";
        $params[] = $data['profile_pic'];
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No fields to update']);
        exit;
    }
    
    $updates[] = "updated_at = NOW()";
    $sql = "UPDATE drivers SET " . implode(", ", $updates) . " WHERE id = ?";
    $types .= "i";
    $params[] = $driver_id;
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'Driver updated successfully',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Driver not found or no changes made']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Update failed']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
// api/log_queue_action.php - Log queue and dispatch actions
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$driver_id = $data['driver_id'] ?? null;
$action = $data['action'] ?? ''; // 'inqueue' or 'dispatch'

if (!$driver_id || !in_array($action, ['inqueue', 'dispatch'])) {
    http_response_code(400);
    echo json_encode(['error' => 'driver_id and valid action required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    if ($action === 'inqueue') {
        // Log queue action in history
        $queue_id = $data['queue_id'] ?? null;
        $driver_name = $data['driver_name'] ?? '';
        $tricycle_number = $data['tricycle_number'] ?? '';
        $queue_time = $data['queue_time'] ?? date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare(
            "INSERT INTO history (driver_id, driver_name, tricycle_number, queue_time, queue_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssi", $driver_id, $driver_name, $tricycle_number, $queue_time, $queue_id);
        
    } else if ($action === 'dispatch') {
        // Log dispatch action in history
        $queue_id = $data['queue_id'] ?? null;
        $driver_name = $data['driver_name'] ?? '';
        $tricycle_number = $data['tricycle_number'] ?? '';
        $dispatch_time = $data['dispatch_time'] ?? date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare(
            "INSERT INTO history (driver_id, driver_name, tricycle_number, dispatch_time, queue_id)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssi", $driver_id, $driver_name, $tricycle_number, $dispatch_time, $queue_id);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => ucfirst($action) . ' logged successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to log action']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
// api/log_removal.php - Log driver removal
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$driver_id = $data['driver_id'] ?? null;
$driver_name = $data['driver_name'] ?? '';
$tricycle_number = $data['tricycle_number'] ?? '';
$queue_number = $data['queue_number'] ?? null;
$remover_driver_id = $data['remover_driver_id'] ?? null;
$remover_driver_name = $data['remover_driver_name'] ?? '';
$reason = $data['reason'] ?? 'Forgot to dispatch - Removed from Now Serving';

if (!$driver_id || !$remover_driver_id) {
    http_response_code(400);
    echo json_encode(['error' => 'driver_id and remover_driver_id required']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare(
        "INSERT INTO removal_logs 
        (driver_id, driver_name, tricycle_number, queue_number, 
         remover_driver_id, remover_driver_name, removed_at, reason)
        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)"
    );
    $stmt->bind_param(
        "issisis", 
        $driver_id, 
        $driver_name, 
        $tricycle_number, 
        $queue_number,
        $remover_driver_id, 
        $remover_driver_name,
        $reason
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Removal logged successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to log removal']);
    }
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
// api/get_removal_logs.php - Get removal logs
require_once '../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$limit = min(max($limit, 1), 500); // Between 1 and 500

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            driver_id,
            driver_name, 
            tricycle_number, 
            queue_number,
            remover_driver_id,
            remover_driver_name,
            removed_at,
            reason
        FROM removal_logs 
        ORDER BY removed_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    echo json_encode([
        'success' => true, 
        'logs' => $logs,
        'count' => count($logs),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

<?php
// api/check_database.php - Test database connection
require_once '../config.php';

try {
    $conn = getDBConnection();
    
    $sql = "SELECT COUNT(*) as driver_count FROM drivers";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    // Check if tables exist
    $tables = ['drivers', 'queue', 'history', 'removal_logs'];
    $existing_tables = [];
    
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            $existing_tables[] = $table;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'database' => DB_NAME,
        'driver_count' => $row['driver_count'],
        'tables_found' => $existing_tables,
        'server_info' => $conn->server_info,
        'timezone' => date('P'),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
