<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../api/auth/auth_guard.php';
include '../../database/db.php';

// Count total registered drivers
$totalDriversQuery = $conn->query("SELECT COUNT(*) as total FROM drivers");
$totalDrivers = $totalDriversQuery->fetch_assoc()['total'] ?? 0;

// Count dispatched drivers today (Active Today)
$activeDriversQuery = $conn->query("
    SELECT COUNT(*) as total 
    FROM queue 
    WHERE status = 'Dispatched' 
    AND DATE(queued_at) = CURDATE()
");
$activeDrivers = $activeDriversQuery->fetch_assoc()['total'] ?? 0;

// Total dispatches today (sum of all dispatches for all drivers today)
$totalDispatchesQuery = $conn->query("
    SELECT COUNT(*) as total_dispatches
    FROM queue
    WHERE status = 'Dispatched' 
    AND DATE(queued_at) = CURDATE()
");
$totalDispatches = $totalDispatchesQuery->fetch_assoc()['total_dispatches'] ?? 0;

// Fetch recent dispatch activity logs from queue table with dispatched count per driver
$logsQuery = $conn->query("
    SELECT 
        d.id AS driver_id,
        d.firstname, 
        d.lastname, 
        d.tricycle_number,
        q.status,
        q.queued_at,
        q.dispatch_at,
        (
            SELECT COUNT(*) 
            FROM queue q2 
            WHERE q2.driver_id = d.id 
            AND q2.status = 'Dispatched'
            AND DATE(q2.queued_at) = CURDATE()
        ) AS dispatched_count
    FROM queue q
    LEFT JOIN drivers d ON q.driver_id = d.id
    WHERE DATE(q.queued_at) = CURDATE()
    ORDER BY q.queued_at DESC
    LIMIT 10
");

// Fetch login logs with admin details
$loginLogsQuery = $conn->query("
    SELECT 
        ll.id,
        ll.username,
        ll.status,
        ll.ip_address,
        ll.user_agent,
        ll.login_time,
        ll.failure_reason,
        CONCAT(a.firstname, ' ', a.lastname) as full_name
    FROM login_logs ll
    LEFT JOIN admins a ON ll.admin_id = a.id
    ORDER BY ll.login_time DESC
    LIMIT 100
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LITODA</title>
    <link rel="stylesheet" href="../../assets/css/navbar/navbar.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
/* ========================================
   MODAL STYLES - BASE
   ======================================== */

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    animation: fadeIn 0.3s ease;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background-color: #f5f5f5;
    border-radius: 8px;
    width: 90%;
    max-width: 1200px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from { 
        transform: translateY(-50px);
        opacity: 0;
    }
    to { 
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    background-color: #fff;
    padding: 20px 30px;
    border-bottom: 2px solid #e0e0e0;
    display: flex;
    justify-content: center;
    align-items: center;
    position: relative;
}

.modal-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 600;
    color: #333;
    text-align: center;
}

.modal-close-btn {
    position: absolute;
    top: 20px;
    right: 30px;
    background: none;
    border: none;
    font-size: 32px;
    color: #666;
    cursor: pointer;
    line-height: 1;
    transition: color 0.3s ease;
}

.modal-close-btn:hover {
    color: #333;
}

.modal-body {
    padding: 20px 30px;
    overflow-y: auto;
    max-height: calc(90vh - 140px);
}

/* ========================================
   LOGIN LOGS TABLE - BASE
   ======================================== */

.login-logs-table {
    width: 100%;
    border-collapse: collapse;
    background-color: #fff;
    border-radius: 8px;
    overflow: hidden;
}

.login-logs-table thead {
    background-color: #8B0000;
}

.login-logs-table thead th {
    color: #fff;
    padding: 15px;
    text-align: left;
    font-weight: 600;
    font-size: 14px;
    text-transform: uppercase;
}

.login-logs-table tbody tr {
    border-bottom: 1px solid #e0e0e0;
    transition: background-color 0.2s ease;
}

.login-logs-table tbody tr:hover {
    background-color: #f9f9f9;
}

.login-logs-table tbody td {
    padding: 15px;
    color: #666;
    font-size: 14px;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: 500;
    display: inline-block;
}

.status-success {
    background-color: #e8f5e9;
    color: #2e7d32;
}

.status-failed {
    background-color: #ffebee;
    color: #c62828;
}


.status-onqueue {
    background-color: #fff3e0;
    color: #e65100;
}

.user-agent {
    font-size: 12px;
    color: #999;
    max-width: 300px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.failure-reason {
    font-size: 12px;
    color: #d32f2f;
    font-style: italic;
}

.full-name {
    font-weight: 500;
    color: #333;
}

.close-btn {
    color: #fff;
    border: none;
    padding: 10px 24px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    margin-top: 20px;
    transition: all 0.3s ease;
}

.btn-view-logs {
    background-color: #10b981;
    color: #fff;
    border: none;
    padding: 12px 24px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    margin-top: 20px;
    transition: background-color 0.3s ease;
}

.btn-view-logs:hover {
    background-color: #059669;
}

.no-data {
    text-align: center;
    padding: 40px;
    color: #999;
    font-size: 16px;
}

/* ========================================
   CONTAINER & MAIN CONTENT - BASE
   ======================================== */

.main-content {
    border: 3px solid #10b981;
    border-radius: 20px;
    padding: 20px;
    margin: 20px;
    background-color: #f0fdf4;
}

.container {
    margin: 40px auto;
    max-width: 1200px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 30px;
    color: #065f46;
    border: 3px solid #10b981;
    border-radius: 20px;
    background-color: #ffffff;
}

/* ========================================
   CARD STYLES - BASE
   ======================================== */

.card {
    background: white;
    border: 3px solid #10b981 !important;
    border-radius: 20px !important;
    padding: 32px;
    width: 95%;
    max-width: 1200px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    margin-bottom: 20px;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2) !important;
    border-color: #065f46 !important;
}

.card-content {
    position: relative;
    z-index: 2;
}

.card-label {
    color: #10b981;
    padding: 8px 16px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    letter-spacing: 0.5px;
    background: #ecfdf5;
    display: inline-block;
    margin-bottom: 10px;
    border: 1px solid #a7f3d0;
}

.card-title {
    color: #065f46;
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 16px 0;
    line-height: 1.3;
    text-align: center;
}

.card-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 40px;
    margin-top: 10px;
    flex-wrap: wrap;
}

.card-data {
    background: white;
    border: 2px solid #10b981 !important;
    border-radius: 15px !important;
    padding: 24px;
    width: 100%;
    max-width: 320px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.card-data:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border-color: #065f46 !important;
}

.card-data-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.card-data-title {
    color: #10b981;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
}

.card-data-p {
    color: #065f46;
    font-size: 15px;
    line-height: 1.4;
    margin: 0;
    font-weight: 400;
}

/* ========================================
   TABLE STYLES - BASE
   ======================================== */

table {
    width: 100%;
    background: white;
    border: 2px solid #10b981;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    border-collapse: collapse;
}

th {
    background: #10b981;
    color: white;
    padding: 16px;
    text-align: left;
    font-weight: 600;
    font-size: 15px;
    letter-spacing: 0.3px;
}

td {
    padding: 16px;
    border-bottom: 1px solid #f3f4f6;
    color: #374151;
    font-size: 14px;
}

tr:hover {
    background: #f9fafb;
}

/* Responsive table wrapper */
.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table-responsive table {
    min-width: 100%;
}

/* Table scroll indicator */
.table-responsive::after {
    content: '← Scroll →';
    display: none;
    position: absolute;
    bottom: 10px;
    right: 10px;
    background: rgba(16, 185, 129, 0.9);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 11px;
    pointer-events: none;
}

@media screen and (max-width: 767px) {
    .table-responsive::after {
        display: block;
    }
}

/* ========================================
   DASHBOARD HEADER - BASE
   ======================================== */

.dashboard-header {
    border: 3px solid #10b981;
    border-radius: 20px;
    background-color: #f0fdf4;
    padding: 30px;
    text-align: center;
    margin: 20px;
}

.dashboard-header h1 {
    color: #10b981;
    font-size: 42px;
    font-weight: 700;
    margin: 0;
    letter-spacing: 2px;
}

.container:hover {
    border-color: #065f46 !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2) !important;
    transition: all 0.3s ease;
}

/* ========================================
   INPUT & FORM ELEMENTS - BASE
   ======================================== */

input[type="text"],
input[type="email"],
input[type="password"],
input[type="number"],
input[type="tel"],
input[type="date"],
input[type="time"],
textarea,
select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #d1d5db;
    border-radius: 10px;
    font-size: 15px;
    color: #374151;
    background-color: #fff;
    transition: all 0.3s ease;
    box-sizing: border-box;
}

input:focus,
textarea:focus,
select:focus {
    outline: none;
    border-color: #10b981;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
}

/* ========================================
   RESPONSIVE - LARGE TABLETS (992px - 1199px)
   ======================================== */

@media screen and (min-width: 992px) and (max-width: 1199px) {
    .container {
        max-width: 960px;
        padding: 25px;
    }

    .card {
        padding: 12px;
    }

    .container {
        gap: 12px;
        padding: 12px;
    }

    input[type="text"],
    input[type="email"],
    input[type="password"],
    textarea,
    select {
        padding: 8px 10px;
        font-size: 12px;
    }

    .card-title {
        font-size: 16px;
    }

    .modal-header {
        padding: 10px 15px;
    }

    .modal-header h2 {
        font-size: 15px;
    }
}

/* ========================================
   TOUCH DEVICE OPTIMIZATIONS
   ======================================== */

@media (hover: none) and (pointer: coarse) {
    /* Increase tap target sizes for touch devices */
    input[type="text"],
    input[type="email"],
    input[type="password"],
    textarea,
    select,
    button {
        min-height: 44px; /* Apple's recommended touch target size */
    }

    .btn-view-logs,
    .close-btn {
        min-height: 44px;
        padding: 12px 20px;
    }

    .modal-close-btn {
        min-width: 44px;
        min-height: 44px;
        padding: 10px;
    }
}

/* ========================================
   PREVENT TEXT ZOOM ON MOBILE
   ======================================== */

@media screen and (max-width: 767px) {
    input[type="text"],
    input[type="email"],
    input[type="password"],
    input[type="number"],
    input[type="tel"],
    textarea,
    select {
        font-size: 16px !important; /* Prevent iOS zoom on focus */
    }
}

/* ========================================
   RESPONSIVE STYLES (keeping existing responsive code)
   ======================================== */

@media screen and (min-width: 768px) and (max-width: 991px) {
    .container {
        max-width: 720px;
        padding: 20px;
        margin: 30px auto;
        gap: 25px;
    }

    .card {
        padding: 24px;
        width: 100%;
    }

    .card-container {
        flex-direction: column;
        align-items: center;
        gap: 20px;
    }
}

@media screen and (max-width: 767px) {
    .dashboard-header h1 {
        font-size: 28px;
    }

    .card-title {
        font-size: 18px;
    }

    th, td {
        padding: 12px 8px;
        font-size: 13px;
    }
}
    </style>
</head>
<body>
<?php include('../../assets/components/navbar.php'); ?>

<div class="main-content">
    <!-- Drivers Overview Cards -->
    <div class="card-content">
        <h2 class="card-title">Drivers Overview</h2>
        <div class="card-container">
            <div class="card-data"> 
                <div class="card-data-content">
                    <div class="card-data-title">Total Registered Drivers:</div>
                    <div class="card-data-p"><?= $totalDrivers ?></div>
                </div>
            </div>
            <div class="card-data"> 
                <div class="card-data-content">
                    <div class="card-data-title">Dispatched Today:</div>
                    <div class="card-data-p"><?= $activeDrivers ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Driver Activity Table -->
    <div class="container">
        <h2>Recent Driver Activity (Today)</h2>
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Driver Name</th>
                        <th>Tricycle No.</th>
                        <th>Queue Time</th>
                        <th>Dispatch Time</th>
                        <th>Status</th>
                        <th>Dispatched Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logsQuery->num_rows > 0): ?>
                        <?php while($row = $logsQuery->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?></td>
                                <td><?= htmlspecialchars($row['tricycle_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars(date("h:i A", strtotime($row['queued_at']))) ?></td>
                                <td><?= $row['dispatch_at'] ? date("h:i A", strtotime($row['dispatch_at'])) : '—' ?></td>
                                <td>
                                    <span class="status-badge <?= $row['status'] == 'Dispatched' ? 'status-dispatched' : 'status-onqueue' ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                                <td style="text-align:center; font-weight:600;">
                                    <?= $row['dispatched_count'] ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align:center;">No activity found today</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Login Logs Modal -->
<div id="loginLogsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Login Logs</h2>
            <button class="modal-close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <table class="login-logs-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Status</th>
                        <th>IP Address</th>
                        <th>Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($loginLogsQuery && $loginLogsQuery->num_rows > 0): ?>
                        <?php while($log = $loginLogsQuery->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if ($log['full_name']): ?>
                                        <div class="full-name"><?= htmlspecialchars($log['full_name']) ?></div>
                                    <?php endif; ?>
                                    <div style="color: #666; font-size: 13px;">@<?= htmlspecialchars($log['username']) ?></div>
                                </td>
                                <td>
                                    <span class="status-badge <?= strtolower($log['status']) == 'success' ? 'status-success' : 'status-failed' ?>">
                                        <?= htmlspecialchars($log['status']) ?>
                                    </span>
                                    <?php if ($log['failure_reason']): ?>
                                        <div class="failure-reason"><?= htmlspecialchars($log['failure_reason']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                    <?php if ($log['user_agent']): ?>
                                        <div class="user-agent" title="<?= htmlspecialchars($log['user_agent']) ?>">
                                            <?= htmlspecialchars($log['user_agent']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(date("M d, Y h:i A", strtotime($log['login_time']))) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="no-data">No login logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="../../assets/js/dashboard/dashboard.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('openLogs') === 'true') {
        setTimeout(() => {
            if (typeof openModal === 'function') openModal();
        }, 300);
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>
</body>
</html>