<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json');

// Include database connection
include '../../database/db.php';

// Initialize response
$response = ['success' => false, 'message' => 'Unknown error'];

try {
    // Check if admin is logged in
    if (empty($_SESSION['admin_id'])) {
        throw new Exception("You are not logged in. Please log in first.");
    }

    $adminId = intval($_SESSION['admin_id']);
    
    // Get and sanitize POST data
    $firstname = trim($_POST['firstname'] ?? '');
    $middlename = trim($_POST['middlename'] ?? '');
    $lastname = trim($_POST['lastname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate required fields
    if (empty($firstname) || empty($lastname) || empty($username)) {
        throw new Exception("First name, last name, and username are required.");
    }

    // Username validation (alphanumeric and underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        throw new Exception("Username can only contain letters, numbers, and underscores.");
    }

    // Check if username is already taken by another admin
    $checkStmt = $conn->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
    $checkStmt->bind_param("si", $username, $adminId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        throw new Exception("Username is already taken by another admin.");
    }
    $checkStmt->close();

    // Get current profile picture from database (not session)
    $currentPicStmt = $conn->prepare("SELECT profile_pic FROM admins WHERE id = ?");
    $currentPicStmt->bind_param("i", $adminId);
    $currentPicStmt->execute();
    $currentPicResult = $currentPicStmt->get_result();
    $currentPicRow = $currentPicResult->fetch_assoc();
    $profile_pic = $currentPicRow['profile_pic'] ?? 'uploads/default.png';
    $currentPicStmt->close();

    // Handle file upload if provided
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../../assets/uploads/';
        
        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $_FILES['profile_pic']['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception("Invalid image type. Only JPG, PNG, and GIF are allowed.");
        }

        // Validate file size (max 5MB)
        if ($_FILES['profile_pic']['size'] > 5 * 1024 * 1024) {
            throw new Exception("Image size must be less than 5MB.");
        }

        // Generate unique filename
        $extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $fileName = 'admin_' . $adminId . '_' . uniqid() . '.' . strtolower($extension);
        $targetPath = $uploadDir . $fileName;

        // Move uploaded file
        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath)) {
            throw new Exception("Failed to upload profile picture. Please try again.");
        }

        // Delete old profile picture if it's not the default
        if (!empty($profile_pic) && $profile_pic !== 'uploads/default.png') {
            $oldPicPath = '../../assets/' . $profile_pic;
            if (file_exists($oldPicPath)) {
                @unlink($oldPicPath);
            }
        }

        // Update profile picture path
        $profile_pic = 'uploads/' . $fileName;
    }

    // Build dynamic UPDATE query
    $updateFields = [];
    $params = [];
    $types = "";

    // Always update these fields
    $updateFields[] = "firstname = ?";
    $params[] = $firstname;
    $types .= "s";

    $updateFields[] = "middlename = ?";
    $params[] = $middlename;
    $types .= "s";

    $updateFields[] = "lastname = ?";
    $params[] = $lastname;
    $types .= "s";

    $updateFields[] = "username = ?";
    $params[] = $username;
    $types .= "s";

    // Update password only if provided
    if (!empty($password)) {
        $updateFields[] = "password = ?";
        $params[] = password_hash($password, PASSWORD_BCRYPT);
        $types .= "s";
    }

    // Update profile picture only if a new one was uploaded
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $updateFields[] = "profile_pic = ?";
        $params[] = $profile_pic;
        $types .= "s";
    }

    // Add admin ID for WHERE clause
    $params[] = $adminId;
    $types .= "i";

    // Prepare and execute UPDATE statement
    $sql = "UPDATE admins SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }

    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        throw new Exception("Failed to update profile: " . $stmt->error);
    }

    $stmt->close();

    // Update ONLY admin-related session variables (don't touch driver sessions)
    $_SESSION['admin_firstname'] = $firstname;
    $_SESSION['admin_middlename'] = $middlename;
    $_SESSION['admin_lastname'] = $lastname;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_picture'] = $profile_pic;

    // Success response
    $response = [
        'success' => true, 
        'message' => 'Profile updated successfully!',
        'data' => [
            'firstname' => $firstname,
            'middlename' => $middlename,
            'lastname' => $lastname,
            'username' => $username,
            'profile_pic' => $profile_pic
        ]
    ];

} catch (Exception $e) {
    $response = [
        'success' => false, 
        'message' => $e->getMessage()
    ];
}

// Close database connection
if (isset($conn)) {
    $conn->close();
}

// Return JSON response
echo json_encode($response);
exit;
?>