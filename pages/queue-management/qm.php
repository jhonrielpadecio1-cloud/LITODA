<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include('../../database/db.php');
require_once '../../api/auth/auth_guard.php';

// Fetch the driver currently driving (first in queue - "Now Serving")
$servingSql = "
    SELECT q.id, q.queue_number, q.status, q.queued_at,
           d.firstname, d.lastname, d.tricycle_number, d.profile_pic
    FROM queue q
    LEFT JOIN drivers d ON q.driver_id = d.id
    WHERE q.status = 'Onqueue'
    AND DATE(q.queued_at) = CURDATE()
    ORDER BY q.queue_number ASC
    LIMIT 1
";
$servingResult = $conn->query($servingSql);
$servingDriver = $servingResult && $servingResult->num_rows > 0 ? $servingResult->fetch_assoc() : null;

// Fetch remaining queued drivers (excluding the first one)
$queueSql = "
    SELECT q.id, q.queue_number, q.status, q.queued_at,
           d.firstname, d.lastname, d.tricycle_number, d.profile_pic
    FROM queue q
    LEFT JOIN drivers d ON q.driver_id = d.id
    WHERE q.status = 'Onqueue'
    AND DATE(q.queued_at) = CURDATE()
    ORDER BY q.queue_number ASC
    LIMIT 999 OFFSET 1
";
$queueResult = $conn->query($queueSql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Queue Management - LTODA</title>
  <link rel="stylesheet" href="../../assets/css/navbar/navbar.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
body {
  margin: 0;
  font-family: "Poppins", sans-serif;
  background: #f5f5f5;
}

.queue-container {
  max-width: 1200px;
  margin: 2rem auto;
  padding: 1.5rem;
  background: #ffffff;
  border-radius: 12px;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
}

.current-serving {
  padding: 2rem;
  background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
  border: 2px solid #10b981;
  border-radius: 8px;
  margin-bottom: 1.5rem;
  text-align: center;
}

.current-serving h3 {
  margin-bottom: 1rem;
  font-size: 1.5rem;
  color: #065f46;
}

.serving-card {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 20px;
  background: #fff;
  padding: 1.5rem;
  border-radius: 10px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.05);
  flex-wrap: wrap;
  position: relative;
}

.queue-number-badge {
   position: absolute;
  top: -12px;
  left: 50%;
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  color: white;
  padding: 6px 18px;
  border-radius: 20px;
  font-size: 1rem;
  font-weight: 700;
  box-shadow: 0 3px 10px rgba(16, 185, 129, 0.4);
  transform: translateX(-50%);
}

.serving-pic {
  width: 90px;
  height: 90px;
  border-radius: 50%;
  object-fit: cover;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

.serving-info h4 {
  margin: 0;
  font-size: 1.2rem;
  color: #1f2937;
}

.serving-info p {
  margin: 0.5rem 0 0 0;
  font-size: 1rem;
  color: #6b7280;
}

/* Table Container for Horizontal Scroll */
.table-container {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.queue-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
  min-width: 650px;
}

.queue-table th,
.queue-table td {
  padding: 12px 10px;
  text-align: center;
  border: 1px solid #ddd;
}

.queue-table th {
  background: #f0fdf4;
  color: #065f46;
  font-weight: 600;
  white-space: nowrap;
}

.queue-number-cell {
  font-size: 1.3rem;
  font-weight: 700;
  color: #10b981;
}

.driver-pic {
  width: 50px;
  height: 50px;
  border-radius: 50%;
  object-fit: cover;
}

.status-badge {
  padding: 5px 12px;
  border-radius: 20px;
  font-size: 0.85rem;
  font-weight: 600;
  background: #fef3c7;
  color: #92400e;
  display: inline-block;
  white-space: nowrap;
}

.next-in-line {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  font-weight: 600;
}

.next-badge {
  display: inline-block;
  background: #f59e0b;
  color: white;
  padding: 3px 10px;
  border-radius: 10px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  margin-left: 5px;
}

.dispatch-btn {
  padding: 8px 16px;
  background: #10b981;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
  font-weight: 600;
  transition: all 0.3s ease;
  font-family: "Poppins", sans-serif;
  white-space: nowrap;
}

.dispatch-btn:hover {
  background: #059669;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.dispatch-btn:active {
  transform: translateY(0);
}

.no-records {
  text-align: center;
  padding: 1rem;
  color: #9ca3af;
}

/* Modal Styles */
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

.modal-content {
  background-color: #ffffff;
  margin: 15% auto;
  padding: 2rem;
  border-radius: 12px;
  width: 90%;
  max-width: 400px;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  animation: slideIn 0.3s ease;
  text-align: center;
}

.modal-header {
  font-size: 1.3rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 1rem;
}

.modal-body {
  font-size: 1rem;
  color: #6b7280;
  margin-bottom: 1.5rem;
}

.modal-buttons {
  display: flex;
  gap: 10px;
  justify-content: center;
  flex-wrap: wrap;
}

.modal-btn {
  padding: 10px 24px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 600;
  font-family: "Poppins", sans-serif;
  transition: all 0.3s ease;
}

.modal-btn-confirm {
  background: #10b981;
  color: white;
}

.modal-btn-confirm:hover {
  background: #059669;
}

.modal-btn-cancel {
  background: #e5e7eb;
  color: #374151;
}

.modal-btn-cancel:hover {
  background: #d1d5db;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideIn {
  from { 
    transform: translateY(-50px);
    opacity: 0;
  }
  to { 
    transform: translateY(0);
    opacity: 1;
  }
}

/* Responsive styles remain the same as before */
@media screen and (max-width: 768px) {
  .queue-container {
    margin: 1rem;
    padding: 1rem;
  }

  .current-serving {
    padding: 1.5rem;
  }

  .serving-card {
    flex-direction: column;
  }

  .queue-number-badge {
    top: -10px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 0.9rem;
    padding: 5px 15px;
  }

  .table-container {
    margin: 0 -1rem;
    padding: 0 1rem;
  }

  .queue-table {
    font-size: 0.85rem;
    min-width: 600px;
  }
}

@media screen and (max-width: 480px) {
  .queue-table {
    font-size: 0.75rem;
    min-width: 550px;
  }

  .queue-number-cell {
    font-size: 1.1rem;
  }
}
  </style>
</head>
<body>
<?php include('../../assets/components/navbar.php'); ?>

<div class="queue-container">
  <div class="current-serving" id="serving-section">
    <h3>Now Serving</h3>
    
    <?php if ($servingDriver): ?>
      <div class="serving-card">
        <div class="queue-number-badge"><?php echo $servingDriver['queue_number'] ?? '?'; ?></div>
        
        <img src="<?php 
          echo !empty($servingDriver['profile_pic']) && file_exists('../../' . $servingDriver['profile_pic']) 
              ? '../../' . $servingDriver['profile_pic'] 
              : '../../assets/img/default-profile.png'; 
        ?>" 
        alt="Profile" class="serving-pic">
        
        <div class="serving-info">
          <h4><?php echo htmlspecialchars($servingDriver['firstname'] . " " . $servingDriver['lastname']); ?></h4>
          <p><?php echo htmlspecialchars($servingDriver['tricycle_number'] ?? 'N/A'); ?></p>
        </div>
        
        <button class="dispatch-btn" onclick="dispatchDriver(<?php echo $servingDriver['id']; ?>, <?php echo $servingDriver['queue_number']; ?>)" style="margin-left: 20px;">
          <i class="fas fa-paper-plane"></i> Dispatch
        </button>
      </div>
    <?php else: ?>
      <p class="no-records"></p>
    <?php endif; ?>
  </div>

  <!-- Table wrapper for responsive scrolling -->
  <div class="table-container">
    <table class="queue-table">
      <thead>
        <tr>
          <th>Queue #</th>
          <th>Profile</th>
          <th>Driver Name</th>
          <th>Tricycle No.</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody id="queue-body">
        <?php if ($queueResult && $queueResult->num_rows > 0): ?>
          <?php $index = 0; ?>
          <?php while ($row = $queueResult->fetch_assoc()): ?>
            <tr class="<?php echo $index === 0 ? 'next-in-line' : ''; ?>">
              <td class="queue-number-cell"><?php echo $row['queue_number'] ?? '?'; ?></td>
              <td>
                <img src="<?php 
                  echo !empty($row['profile_pic']) && file_exists('../../' . $row['profile_pic']) 
                      ? '../../' . $row['profile_pic'] 
                      : '../../assets/img/default-profile.png'; 
                ?>" 
                alt="Profile" class="driver-pic">
              </td>
              <td>
                <?php echo htmlspecialchars($row['firstname'] . " " . $row['lastname']); ?>
                <?php if ($index === 0): ?>
                  <span class="next-badge">NEXT</span>
                <?php endif; ?>
              </td>
              <td><?php echo htmlspecialchars($row['tricycle_number'] ?? 'N/A'); ?></td>
              <td><span class="status-badge">Waiting</span></td>
              <td>
                <button class="dispatch-btn" onclick="dispatchDriver(<?php echo $row['id']; ?>, <?php echo $row['queue_number']; ?>)">
                  <i class="fas fa-paper-plane"></i> Dispatch
                </button>
              </td>
            </tr>
            <?php $index++; ?>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="no-records">No drivers waiting in queue</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Dispatch Confirmation Modal -->
<div id="dispatchModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <i class="fas fa-paper-plane" style="color: #10b981; margin-right: 8px;"></i>
      Confirm Dispatch
    </div>
    <div class="modal-body" id="modal-message">
      Are you sure you want to dispatch this driver?
    </div>
    <div class="modal-buttons">
      <button class="modal-btn modal-btn-cancel" onclick="closeModal()">Cancel</button>
      <button class="modal-btn modal-btn-confirm" onclick="confirmDispatch()">Dispatch</button>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let currentDispatchQueueId = null;
let currentQueueNumber = null;
let refreshInterval;

function dispatchDriver(queueId, queueNumber) {
  currentDispatchQueueId = queueId;
  currentQueueNumber = queueNumber;
  
  document.getElementById('modal-message').textContent = 
    `Are you sure you want to dispatch Queue #${queueNumber}?`;
  document.getElementById('dispatchModal').style.display = 'block';
  
  clearInterval(refreshInterval);
}

function closeModal() {
  document.getElementById('dispatchModal').style.display = 'none';
  currentDispatchQueueId = null;
  currentQueueNumber = null;
  startAutoRefresh();
}

window.onclick = function(event) {
  const modal = document.getElementById('dispatchModal');
  if (event.target == modal) {
    closeModal();
  }
}

function confirmDispatch() {
  if (!currentDispatchQueueId) return;

  $.ajax({
    url: '../../api/auth/dispatch_driver.php',
    type: 'POST',
    data: { 
      queue_id: currentDispatchQueueId,
      action: 'dispatch'
    },
    dataType: 'json',
    success: function(response) {
      closeModal();
      if (response.success) {
        showNotification(`✅ dispatched successfully!`, 'success');
        fetchQueue(); // Update queue immediately
      } else {
        showNotification('❌ Error: ' + (response.message || 'Failed to dispatch driver'), 'error');
      }
    },
    error: function() {
      closeModal();
      showNotification('❌ Error: Unable to dispatch driver.', 'error');
    }
  });
}

function fetchQueue() {
  $.ajax({
    url: '../../api/auth/LQ.php',
    type: 'GET',
    data: { action: 'fetch' },
    dataType: 'json',
    success: function(response) {
      if (response.success && response.data) {
        updateQueueDisplay(response.data);
      }
    },
    error: function(xhr, status, error) {
      console.error('Error fetching queue:', error);
    }
  });
}

function updateQueueDisplay(queueData) {
  const onqueueDrivers = queueData.filter(d => d.status === 'Onqueue');
  
  // Update "Now Serving"
  if (onqueueDrivers.length > 0) {
    const serving = onqueueDrivers[0];
    const servingHTML = `
      <h3>Now Serving</h3>
      <div class="serving-card">
        <div class="queue-number-badge">${serving.queue_number || '?'}</div>
        <img src="${serving.profile_pic ? '../../' + serving.profile_pic : '../../assets/img/default-profile.png'}" 
             alt="Profile" class="serving-pic" onerror="this.src='../../assets/img/default-profile.png'">
        <div class="serving-info">
          <h4>${serving.firstname} ${serving.lastname}</h4>
          <p>${serving.tricycle_number || 'N/A'}</p>
        </div>
        <button class="dispatch-btn" onclick="dispatchDriver(${serving.id}, ${serving.queue_number})" style="margin-left: 20px;">
          <i class="fas fa-paper-plane"></i> Dispatch
        </button>
      </div>
    `;
    $('#serving-section').html(servingHTML);
    
    // Update waiting queue
    const waiting = onqueueDrivers.slice(1);
    let tableHTML = '';
    waiting.forEach((driver, index) => {
      const nextClass = index === 0 ? 'next-in-line' : '';
      const nextBadge = index === 0 ? '<span class="next-badge">NEXT</span>' : '';
      tableHTML += `
        <tr class="${nextClass}">
          <td class="queue-number-cell">${driver.queue_number || '?'}</td>
          <td><img src="${driver.profile_pic ? '../../' + driver.profile_pic : '../../assets/img/default-profile.png'}" 
                   alt="Profile" class="driver-pic" onerror="this.src='../../assets/img/default-profile.png'"></td>
          <td>${driver.firstname} ${driver.lastname}${nextBadge}</td>
          <td>${driver.tricycle_number || 'N/A'}</td>
          <td><span class="status-badge">Waiting</span></td>
          <td><button class="dispatch-btn" onclick="dispatchDriver(${driver.id}, ${driver.queue_number})">
                <i class="fas fa-paper-plane"></i> Dispatch
              </button></td>
        </tr>
      `;
    });
    
    if (tableHTML === '') {
      tableHTML = '<tr><td colspan="6" class="no-records">No drivers waiting in queue</td></tr>';
    }
    $('#queue-body').html(tableHTML);
    
  } else {
    $('#serving-section').html('<h3>Now Serving</h3><p class="no-records"></p>');
    $('#queue-body').html('<tr><td colspan="6" class="no-records">No drivers waiting in queue</td></tr>');
  }
}

function showNotification(message, type) {
  $('.notification-toast').remove();
  const bgColor = type === 'success' ? '#10b981' : '#ef4444';
  const notification = $('<div>', {
    class: 'notification-toast',
    html: message,
    css: {
      position: 'fixed', top: '20px', right: '20px',
      background: bgColor, color: 'white',
      padding: '16px 24px', borderRadius: '8px',
      boxShadow: '0 4px 12px rgba(0,0,0,0.15)', zIndex: 10000,
      fontSize: '0.95rem', fontWeight: '600', animation: 'slideInRight 0.3s ease',
      maxWidth: '400px'
    }
  });
  $('body').append(notification);
  setTimeout(() => { notification.fadeOut(300, function() { $(this).remove(); }); }, 4000);
}
  
function startAutoRefresh() {
  clearInterval(refreshInterval);
  refreshInterval = setInterval(fetchQueue, 5000);
}

$(document).ready(function() {
  console.log('🚀 LTODA Queue Management System Started');
  console.log('🔢 Queue Numbers: ENABLED');
  fetchQueue(); // Initial fetch
  startAutoRefresh();
});
</script>
</body>
</html>