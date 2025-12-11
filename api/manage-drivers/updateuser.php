<?php
require_once '../../database/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $driver_id = intval($_POST['driver_id']);
    $firstname = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename']);
    $lastname = trim($_POST['lastname']);
    $platenumber = trim($_POST['platenumber']);
    $contact = !empty($_POST['contact']) ? $_POST['contact'] : null;
    $existing_image = $_POST['existing_image'];

    // Required fields
    if (empty($firstname) || empty($lastname) || empty($platenumber)) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=missing_fields");
        exit();
    }

    // Validate contact only if provided
    if (!empty($contact) && !preg_match('/^[0-9]{11}$/', $contact)) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=invalid_contact");
        exit();
    }

    // Optional → save NULL
    $contact = !empty($contact) ? $contact : null;


    /** ======================
     *  IMAGE VALIDATION + UPDATE
     *  ====================== */
     
    $profile_picture_path = $existing_image;

    if (!empty($_POST['profile_image'])) {

        $base64_image = $_POST['profile_image'];
        $base64_image = substr($base64_image, strpos($base64_image, ',') + 1);
        $decoded = base64_decode($base64_image);

        if ($decoded === false) {
            header("Location: ../../pages/manage-drivers/managedrivers.php?error=invalid_image_data");
            exit();
        }

        // Validate exactly one face
        $validate = json_encode(["image" => $_POST['profile_image']]);

        $ch = curl_init("http://127.0.0.1:5000/validate_single_face");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $validate);
        $validate_res = curl_exec($ch);
        curl_close($ch);

        $validate_json = json_decode($validate_res, true);

        if (!$validate_json["valid"]) {
            header("Location: ../../pages/manage-drivers/managedrivers.php?error=face_validation_failed");
            exit();
        }

        // Compare with existing image (to ensure same person)
        if (!empty($existing_image) && file_exists('../../' . $existing_image)) {

            $payload = json_encode([
                "existing_image_path" => realpath('../../' . $existing_image),
                "new_image" => $_POST['profile_image']
            ]);
            $ch2 = curl_init("http://127.0.0.1:5000/check_face_match");
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $payload);
            $match_res = curl_exec($ch2);
            curl_close($ch2);

            $match_json = json_decode($match_res, true);

            if ($match_json["same_face"] === false) {
                header("Location: ../../pages/manage-drivers/managedrivers.php?error=face_mismatch");
                exit();
            }
        }

        // Delete old image
        if (!empty($existing_image) && file_exists('../../' . $existing_image)) {
            unlink('../../' . $existing_image);
        }

        // Save new image
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '', ucfirst($firstname) . '_' . ucfirst($lastname));
        $upload_dir = '../../uploads/' . $folder_name . '/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $filename = 'profile_' . uniqid() . '_' . time() . '.jpg';
        $file_path = $upload_dir . $filename;

        file_put_contents($file_path, $decoded);

        $profile_picture_path = 'uploads/' . $folder_name . '/' . $filename;
    }


    /** ======================
     *  UPDATE DRIVER
     *  ====================== */
    
    $stmt = $conn->prepare("
        UPDATE drivers 
        SET firstname=?, middlename=?, lastname=?, tricycle_number=?, contact_no=?, profile_pic=? 
        WHERE id=?
    ");
    $stmt->bind_param("ssssssi", $firstname, $middlename, $lastname, $platenumber, $contact, $profile_picture_path, $driver_id);

    if ($stmt->execute()) {
        header("Location: ../../pages/manage-drivers/managedrivers.php?success=user_updated");
    } else {
        header("Location: ../../pages/manage-drivers/managedrivers.php?error=update_failed");
    }

    exit();
}
?>
