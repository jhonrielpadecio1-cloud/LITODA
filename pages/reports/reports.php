<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../../api/auth/auth_guard.php';
include('../../database/db.php');

// Set timezone
date_default_timezone_set('Asia/Manila');
$conn->query("SET time_zone = '+08:00'");

// Get filter parameters
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$filterDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$searchDriver = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pagination setup
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$whereConditions = [];
if (!empty($filterDate)) {
    $whereConditions[] = "DATE(q.queued_at) = '" . $conn->real_escape_string($filterDate) . "'";
}
if (!empty($searchDriver)) {
    $searchEscaped = $conn->real_escape_string($searchDriver);
    $whereConditions[] = "(d.firstname LIKE '%$searchEscaped%' 
                          OR d.lastname LIKE '%$searchEscaped%' 
                          OR d.tricycle_number LIKE '%$searchEscaped%')";
}
$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : '';

// Get summary counts
$summaryQuery = "
    SELECT 
        COUNT(*) as total_queued,
        SUM(CASE WHEN status = 'Dispatched' THEN 1 ELSE 0 END) as total_dispatched,
        SUM(CASE WHEN status = 'Removed' THEN 1 ELSE 0 END) as total_removed
    FROM queue q
    INNER JOIN drivers d ON q.driver_id = d.id
    $whereClause
";
$summaryResult = $conn->query($summaryQuery);
$summary = $summaryResult->fetch_assoc();

// Main query
$sql = "
    SELECT q.id, q.status, q.queued_at, q.dispatch_at,
           d.firstname, d.lastname, d.tricycle_number, d.profile_pic
    FROM queue q
    INNER JOIN drivers d ON q.driver_id = d.id
    $whereClause
    ORDER BY q.queued_at DESC
    LIMIT $limit OFFSET $offset
";
$result = $conn->query($sql);

// Apply filter logic
$filteredResults = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        if ($filterStatus === 'all') {
            $filteredResults[] = $row;
        } elseif ($filterStatus === 'in' && $row['status'] !== 'Dispatched') {
            $filteredResults[] = $row;
        } elseif ($filterStatus === 'out' && $row['status'] === 'Dispatched') {
            $filteredResults[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Queue Reports - LTODA</title>
    <link rel="stylesheet" href="../../assets/css/navbar/navbar.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
*{margin:0;padding:0;box-sizing:border-box;}
body{
    font-family:"Poppins",sans-serif;
    background:#f5f7fa;
    min-height:100vh;
}

.container{
    max-width:1400px;
    margin:0 auto;
    padding:2rem;
}

/* PAGE HEADER */
.page-header{
    margin-bottom:2rem;
}

.page-header h1{
    font-size:2rem;
    color:#065f46;
    margin-bottom:0.5rem;
}

.page-header p{
    color:#6b7280;
    font-size:0.95rem;
}

/* SUMMARY CARDS */
.summary-section{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));
    gap:1.5rem;
    margin-bottom:2rem;
}

.summary-card{
    background:white;
    border-radius:12px;
    padding:1.5rem;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
    border-left:4px solid;
    transition:transform 0.2s;
}

.summary-card:hover{
    transform:translateY(-5px);
}

.summary-card.total{
    border-left-color:#3b82f6;
    background:linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}

.summary-card.dispatched{
    border-left-color:#10b981;
    background:linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
}

.summary-card.removed{
    border-left-color:#ef4444;
    background:linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
}

.summary-card-header{
    display:flex;
    align-items:center;
    gap:0.75rem;
    margin-bottom:0.75rem;
}

.summary-icon{
    width:48px;
    height:48px;
    border-radius:10px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.5rem;
}

.summary-card.total .summary-icon{
    background:#3b82f6;
    color:white;
}

.summary-card.dispatched .summary-icon{
    background:#10b981;
    color:white;
}

.summary-card.removed .summary-icon{
    background:#ef4444;
    color:white;
}

.summary-label{
    font-size:0.85rem;
    color:#6b7280;
    font-weight:500;
    text-transform:uppercase;
    letter-spacing:0.5px;
}

.summary-value{
    font-size:2.5rem;
    font-weight:700;
    line-height:1;
}

.summary-card.total .summary-value{
    color:#3b82f6;
}

.summary-card.dispatched .summary-value{
    color:#10b981;
}

.summary-card.removed .summary-value{
    color:#ef4444;
}

.summary-description{
    font-size:0.85rem;
    color:#6b7280;
    margin-top:0.5rem;
}

/* FILTER SECTION */
.filter-section{
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    padding:1.5rem;
    border-radius:12px;
    border: 2px solid #10b981;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
    margin-bottom:2rem;
}
.filter-form{
    display:grid;
    grid-template-columns:200px 400px 1fr auto;
    gap:1rem;
    align-items:end;
}

.form-group{display:flex;flex-direction:column;}
.form-group label{
    font-size:0.85rem;
    font-weight:500;
    color:#374151;
    margin-bottom:0.5rem;
}

.form-control{
    padding:0.7rem 1rem;
    border: 2px solid #10b981;
    border-radius:12px;
    font-size:0.95rem;
    background:white;
    transition:all 0.2s ease;
}

.form-control:focus{
    border: 2px solid #10b981;
    outline:none;
    box-shadow:0 0 0 3px rgba(16,185,129,0.12);
}

/* BUTTONS */
.btn{
    padding:0.7rem 1.5rem;
    border:none;
    border-radius:12px;
    font-size:0.95rem;
    font-weight:500;
    cursor:pointer;
    transition:all 0.2s;
    display:inline-flex;
    align-items:center;
    gap:0.5rem;
    text-decoration:none;
}

.btn-primary{
    background:linear-gradient(135deg,#10b981 0%,#059669 100%);
    color:white;
}
.btn-primary:hover{
    transform:translateY(-2px);
    box-shadow:0 4px 12px rgba(16,185,129,0.3);
}

.button-group{
    display:flex;
    justify-content:flex-end;
    gap:0.75rem;
    flex-wrap:wrap;
}

.btn-secondary{
    background:white;
    color:#374151;
    border:1px solid #d1d5db;
}
.btn-secondary:hover{background:#f9fafb;}

.btn-print{
    background:linear-gradient(135deg,#10b981 0%,#059669 100%);
    color:white;
}

/* TABLE SECTION */
.table-section{
    background:white;
    border-radius:12px;
    border: 2px solid #10b981;
    box-shadow:0 4px 20px rgba(0,0,0,0.08);
    overflow:hidden;
}

.table-responsive{
    width:100%;
    overflow-x:auto;
    -webkit-overflow-scrolling:touch;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:800px;
}

thead{
    background:linear-gradient(135deg,#ecfdf5 0%,#d1fae5 100%);
}

th{
    padding:1rem;
    text-align:left;
    font-size:0.85rem;
    font-weight:600;
    color:#065f46;
    text-transform:uppercase;
    border-bottom:2px solid #10b981;
    white-space:nowrap;
}

td{
    padding:1rem;
    border-bottom:1px solid #f1f3f4;
    font-size:0.9rem;
    color:#374151;
}

tbody tr:hover{
    background:#f9fafb;
}

/* DRIVER INFO */
.driver-info{
    display:flex;
    align-items:center;
    gap:0.75rem;
}

.driver-pic{
    width:45px;
    height:45px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid #e5e7eb;
    flex-shrink:0;
}

.driver-placeholder{
    width:45px;
    height:45px;
    background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    color:white;
    font-weight:600;
    flex-shrink:0;
}

.no-records{
    text-align:center;
    padding:3rem;
    color:#9ca3af;
}
.no-records i{
    font-size:3rem;
    margin-bottom:1rem;
    opacity:0.3;
}

/* STATUS BADGE */
.status-badge{
    padding:0.4rem 0.8rem;
    border-radius:20px;
    font-size:0.8rem;
    font-weight:500;
    display:inline-block;
}

.status-badge.dispatched{
    background:#d1fae5;
    color:#065f46;
}

.status-badge.pending{
    background:#fef3c7;
    color:#92400e;
}

.status-badge.removed{
    background:#fee2e2;
    color:#991b1b;
}

/* RESPONSIVE DESIGN */
@media screen and (max-width: 1024px) {
    .container{padding:1.5rem;}
    
    .summary-section{
        grid-template-columns:repeat(auto-fit, minmax(250px, 1fr));
        gap:1rem;
    }

    .filter-form{
        grid-template-columns:1fr 1fr;
        gap:1rem;
    }

    .button-group{
        grid-column:1 / -1;
        justify-content:center;
    }
}

@media screen and (max-width: 768px) {
    .container{padding:1rem;}
    
    .page-header h1{font-size:1.5rem;}
    
    .summary-section{
        grid-template-columns:1fr;
        gap:1rem;
    }
    
    .summary-card{
        padding:1.25rem;
    }
    
    .summary-value{
        font-size:2rem;
    }

    .filter-section{padding:1rem;}

    .filter-form{
        grid-template-columns:1fr;
        gap:0.75rem;
    }

    .button-group{
        flex-direction:column;
        gap:0.5rem;
    }

    .btn{
        width:100%;
        justify-content:center;
        padding:0.6rem 1rem;
        font-size:0.9rem;
    }

    .table-responsive{
        margin:0 -1rem;
        padding:0 1rem;
    }

    table{
        font-size:0.85rem;
        min-width:650px;
    }

    th{
        padding:0.75rem 0.5rem;
        font-size:0.75rem;
    }

    td{padding:0.75rem 0.5rem;}

    .driver-pic,
    .driver-placeholder{
        width:35px;
        height:35px;
    }
}

@media screen and (max-width: 480px) {
    .container{padding:0.75rem;}
    
    .page-header{margin-bottom:1.5rem;}
    .page-header h1{font-size:1.25rem;}
    
    .summary-section{gap:0.75rem;}
    
    .summary-card{padding:1rem;}
    
    .summary-icon{
        width:40px;
        height:40px;
        font-size:1.25rem;
    }
    
    .summary-value{font-size:1.75rem;}

    table{
        font-size:0.75rem;
        min-width:600px;
    }

    th{
        padding:0.6rem 0.4rem;
        font-size:0.7rem;
    }

    td{padding:0.6rem 0.4rem;}

    .driver-pic,
    .driver-placeholder{
        width:30px;
        height:30px;
        font-size:0.75rem;
    }
}

/* PRINT MODE */
@media print {
    body { background:white; }
    nav, .filter-section, .btn, .button-group, .summary-section { display:none !important; }
    .container { max-width:100%; padding:0; margin:0; }
    .page-header { margin-bottom:1rem; }
    .table-section { box-shadow:none; border:1px solid #e5e7eb; margin-top:0; }
    table { font-size:0.9rem; min-width:auto; }
    th { background:#10b981 !important; color:white !important; }
    @page { margin:1cm; }
}
  </style>
</head>
<body>
<?php include('../../assets/components/navbar.php'); ?>

<div class="container">

    <!-- Summary Cards -->
    <div class="summary-section">
        <div class="summary-card total">
            <div class="summary-card-header">
                <div class="summary-icon">
                    <i class="fa-solid fa-list"></i>
                </div>
                <div class="summary-label">Total Queued</div>
            </div>
            <div class="summary-value"><?= number_format($summary['total_queued'] ?? 0); ?></div>
            <div class="summary-description">Drivers for <?= date('M d, Y', strtotime($filterDate)); ?></div>
        </div>

        <div class="summary-card dispatched">
            <div class="summary-card-header">
                <div class="summary-icon">
                    <i class="fa-solid fa-check-circle"></i>
                </div>
                <div class="summary-label">Dispatched</div>
            </div>
            <div class="summary-value"><?= number_format($summary['total_dispatched'] ?? 0); ?></div>
            <div class="summary-description">Successfully dispatched</div>
        </div>

        <div class="summary-card removed">
            <div class="summary-card-header">
                <div class="summary-icon">
                    <i class="fa-solid fa-user-xmark"></i>
                </div>
                <div class="summary-label">Removed</div>
            </div>
            <div class="summary-value"><?= number_format($summary['total_removed'] ?? 0); ?></div>
            <div class="summary-description">Forgot to dispatch</div>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
        <form class="filter-form" method="GET" action="reports.php">
            <div class="form-group">
                <label for="date">Select Date</label>
                <input type="date" id="date" name="date" class="form-control" 
                    value="<?= htmlspecialchars($filterDate); ?>" 
                    max="<?= date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label for="search">Search Driver</label>
                <input type="text" id="search" name="search" class="form-control" 
                    placeholder="Name or Plate Number..." 
                    value="<?= htmlspecialchars($searchDriver); ?>">
            </div>
            <div class="button-group">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Filter</button>
                <a href="reports.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-right"></i> Reset</a>
                <button type="button" class="btn btn-print" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
            </div>
        </form>
    </div>

    <!-- Table Section -->
    <div class="table-section">
        <div class="table-responsive">
            <table id="reportsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Driver Name</th>
                        <th>Plate Number</th>
                        <th>Queue Time</th>
                        <th>Dispatch Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($filteredResults)): ?>
                        <?php $count = 1; foreach ($filteredResults as $row): ?>
                            <tr>
                                <td><?= $count++; ?></td>
                                <td>
                                    <div class="driver-info">
                                        <?php if (!empty($row['profile_pic']) && file_exists('../../' . $row['profile_pic'])): ?>
                                            <img src="<?= '../../' . htmlspecialchars($row['profile_pic']); ?>" class="driver-pic" alt="Driver">
                                        <?php else: ?>
                                            <div class="driver-placeholder">
                                                <?= strtoupper(substr($row['firstname'], 0, 1) . substr($row['lastname'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']); ?></span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($row['tricycle_number']); ?></td>
                                <td><?= date('M d, Y h:i A', strtotime($row['queued_at'])); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Dispatched' && !empty($row['dispatch_at'])): ?>
                                        <?= date('M d, Y h:i A', strtotime($row['dispatch_at'])); ?>
                                    <?php elseif ($row['status'] === 'Removed' && !empty($row['dispatch_at'])): ?>
                                        <?= date('M d, Y h:i A', strtotime($row['dispatch_at'])); ?>
                                    <?php else: ?>
                                        <span style="color:#9ca3af;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Dispatched'): ?>
                                        <span class="status-badge dispatched">
                                            <i class="fa-solid fa-check"></i> Dispatched
                                        </span>
                                    <?php elseif ($row['status'] === 'Removed'): ?>
                                        <span class="status-badge removed">
                                            <i class="fa-solid fa-xmark"></i> Removed
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge pending">
                                            <i class="fa-solid fa-clock"></i> Queued
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>   
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-records">
                                <i class="fa-solid fa-inbox"></i><br>No records found for selected filters
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://kit.fontawesome.com/4c7e22a859.js" crossorigin="anonymous"></script>
<script src="../../assets/js/reports/reports.js"></script>
</body>
</html>