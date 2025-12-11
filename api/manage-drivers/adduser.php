<?php
require_once '../../database/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $platenumber = trim($_POST['platenumber']);
    $contact = !empty($_POST['contact']) ? $_POST['contact'] : null;

    // Validate required fields (contact optional)
    if (empty($firstname) || empty($lastname) || empty($platenumber)) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=missing_fields");
        exit();
    }

    // Validate contact number only if entered
    if (!empty($contact) && !preg_match('/^[0-9]{11}$/', $contact)) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=invalid_contact");
        exit();
    }

    // OPTIONAL CONTACT = store NULL instead of empty string
    $contact = !empty($contact) ? $contact : null;

    // Check duplicate by name + plate
    $duplicateCheck = $conn->prepare("
        SELECT id FROM drivers 
        WHERE LOWER(TRIM(firstname)) = LOWER(TRIM(?))
        AND LOWER(TRIM(lastname)) = LOWER(TRIM(?))
        AND LOWER(TRIM(tricycle_number)) = LOWER(TRIM(?))
    ");
    $duplicateCheck->bind_param("sss", $firstname, $lastname, $platenumber);
    $duplicateCheck->execute();
    $duplicateResult = $duplicateCheck->get_result();
    
    if ($duplicateResult->num_rows > 0) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=duplicate_driver");
        exit();
    }

    // Handle profile image upload
    if (empty($_POST['profile_image'])) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=no_image_provided");
        exit();
    }

    $base64_image = $_POST['profile_image'];
    $profile_picture_path = null;

    // Validate base64
    if (preg_match('/^data:image\/(\w+);base64,/', $base64_image, $type)) {
        $image_type = strtolower($type[1]);
        if (!in_array($image_type, ['jpg', 'jpeg', 'png', 'gif'])) {
            header("Location: ../../pages/manage-drivers/managedrivers.php?error=invalid_image_type");
            exit();
        }

        $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
        $decoded_image_data = base64_decode($base64_image);

        if ($decoded_image_data === false) {
            header("Location: ../../pages/manage-drivers/managedrivers.php?error=invalid_image_data");
            exit();
        }

        // Create folder
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', ucfirst($firstname) . '_' . ucfirst($lastname));
        $upload_dir = '../../uploads/' . $folder_name . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        // Save file
        $filename = 'profile_' . uniqid() . '_' . time() . '.' . ($image_type === 'jpeg' ? 'jpg' : $image_type);
        $file_path = $upload_dir . $filename;

        if (file_put_contents($file_path, $decoded_image_data)) {
            $profile_picture_path = 'uploads/' . $folder_name . '/' . $filename;
        } else {
            header("Location: ../../pages/manage-drivers/managedrivers.php?error=file_upload_failed");
            exit();
        }
    }

    // Insert into DB
    $stmt = $conn->prepare("
        INSERT INTO drivers (firstname, middlename, lastname, tricycle_number, contact_no, profile_pic)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssss", $firstname, $middlename, $lastname, $platenumber, $contact, $profile_picture_path);

    if (!$stmt->execute()) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=database_insert_failed");
        exit();
    }

    header("Location: ../../pages/manage-drivers/managedrivers.php?success=user_added");
    exit();
}
?>
