<?php
header('Content-Type: application/json');
include('../../database/db.php');

// ============================================
// PHILSMS CONFIGURATION
// ============================================
define('PHILSMS_API_KEY', '456|1mtvMnSeyJkCzlVpXxxgRb2hGg9uXpHUeRKSPIlod9ae86d7'); // Your PhilSMS API Token
define('PHILSMS_SENDER_NAME', 'PhilSMS'); // Default sender (Globe only)

/**
 * Format phone number for Philippines - ENHANCED
 */
function formatPhoneNumber($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);

    // Handle different formats
    if (substr($phone, 0, 1) == '0') {
        // 09171234567 -> 639171234567
        return '63' . substr($phone, 1);
    }
    if (substr($phone, 0, 3) == '+63') {
        // +639171234567 -> 639171234567
        return substr($phone, 1);
    }
    if (substr($phone, 0, 2) == '63') {
        // Already in correct format
        return $phone;
    }
    if (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
        // 9171234567 -> 639171234567
        return '63' . $phone;
    }

    return $phone;
}

/**
 * Validate Philippine mobile number
 */
function isValidPhilippineMobile($phone) {
    // After formatting, should be 12 digits starting with 63
    if (strlen($phone) != 12) return false;
    if (substr($phone, 0, 2) != '63') return false;
    
    // Valid prefixes after 63: 9XX (mobile)
    $prefix = substr($phone, 2, 1);
    return $prefix == '9';
}

/**
 * Send SMS using PhilSMS API - ENHANCED WITH DEBUGGING
 */
function sendPhilSMS($toNumber, $message) {
    $apiKey = PHILSMS_API_KEY;
    $sender = PHILSMS_SENDER_NAME;

    $url = "https://dashboard.philsms.com/api/v3/sms/send";
    
    $data = [
        'recipient' => $toNumber,
        'sender_id' => $sender,
        'type' => 'plain',
        'message' => $message
    ];

    // Log the request for debugging
    error_log("PhilSMS Request: " . json_encode($data));

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Changed to true for security
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Add timeout
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log response for debugging
    error_log("PhilSMS Response Code: $httpCode");
    error_log("PhilSMS Response: " . $response);
    if ($curlError) {
        error_log("PhilSMS cURL Error: " . $curlError);
    }

    $responseData = json_decode($response, true);

    return [
        'success' => ($httpCode == 200 || $httpCode == 201),
        'http_code' => $httpCode,
        'response' => $responseData,
        'curl_error' => $curlError,
        'raw_response' => $response
    ];
}

/**
 * Log SMS to database
 */
function logSMS($conn, $driverId, $phoneNumber, $message, $status, $response) {
    $responseJson = is_string($response) ? $response : json_encode($response);
    
    $sql = "INSERT INTO sms_logs (driver_id, phone_number, message, status, response, sent_at) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("issss", $driverId, $phoneNumber, $message, $status, $responseJson);
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("SMS Log Error: " . $stmt->error);
        }
        
        $stmt->close();
        return $result;
    } else {
        error_log("SMS Log Prepare Error: " . $conn->error);
        return false;
    }
}

/**
 * Check if SMS was recently sent to prevent duplicates
 */
function wasRecentlySent($conn, $driverId, $minutes = 5) {
    $sql = "SELECT id FROM sms_logs 
            WHERE driver_id = ? 
            AND status = 'sent' 
            AND sent_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
            ORDER BY sent_at DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ii", $driverId, $minutes);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
        return $exists;
    }
    return false;
}

// ============================================
// MAIN SMS SENDING LOGIC - ENHANCED
// ============================================
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $driverId = isset($_POST['driver_id']) ? intval($_POST['driver_id']) : 0;
    $driverName = isset($_POST['driver_name']) ? trim($_POST['driver_name']) : '';
    $tricycleNumber = isset($_POST['tricycle_number']) ? trim($_POST['tricycle_number']) : '';
    $contactNo = isset($_POST['contact_no']) ? trim($_POST['contact_no']) : '';

    // Enhanced validation
    if (empty($driverId)) {
        throw new Exception('Driver ID is required');
    }
    
    if (empty($driverName)) {
        throw new Exception('Driver name is required');
    }
    
    if (empty($contactNo)) {
        throw new Exception('Contact number is required');
    }

    // Check for recent duplicates
    if (wasRecentlySent($conn, $driverId, 5)) {
        echo json_encode([
            'success' => false,
            'message' => 'SMS already sent to this driver recently',
            'duplicate' => true
        ]);
        exit;
    }

    // Format and validate phone number
    $formattedPhone = formatPhoneNumber($contactNo);
    
    if (!isValidPhilippineMobile($formattedPhone)) {
        throw new Exception("Invalid Philippine mobile number format: $contactNo (formatted: $formattedPhone)");
    }

    // Create message
    $message = "Hello {$driverName}! You are the NEXT driver in the queue";
    if (!empty($tricycleNumber)) {
        $message .= " (Tricycle {$tricycleNumber})";
    }
    $message .= ". Please prepare and proceed to the Libas Terminal. Thank you! - LITODA ";

    // Send SMS
    $result = sendPhilSMS($formattedPhone, $message);
    $status = $result['success'] ? 'sent' : 'failed';

    // Log to database
    $logResult = logSMS($conn, $driverId, $formattedPhone, $message, $status, $result);

    if ($result['success']) {
        $response = [
            'success' => true,
            'message' => 'SMS sent successfully',
            'driver_name' => $driverName,
            'phone_number' => $formattedPhone,
            'sms_message' => $message,
            'timestamp' => date('Y-m-d H:i:s'),
            'logged' => $logResult
        ];
    } else {
        // Enhanced error response
        $errorMsg = 'Failed to send SMS';
        if (isset($result['response']['message'])) {
            $errorMsg = $result['response']['message'];
        } elseif (!empty($result['curl_error'])) {
            $errorMsg = 'Network error: ' . $result['curl_error'];
        } elseif ($result['http_code'] == 401) {
            $errorMsg = 'Authentication failed - Check API key';
        } elseif ($result['http_code'] == 400) {
            $errorMsg = 'Bad request - Check phone number format';
        } elseif ($result['http_code'] == 403) {
            $errorMsg = 'Insufficient credits or unauthorized';
        }

        $response = [
            'success' => false,
            'message' => $errorMsg,
            'http_code' => $result['http_code'],
            'phone_formatted' => $formattedPhone,
            'phone_original' => $contactNo,
            'error_details' => $result['response'],
            'curl_error' => $result['curl_error'],
            'logged' => $logResult
        ];
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
}

$conn->close();
?>