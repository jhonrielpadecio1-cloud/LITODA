let currentDispatchQueueId = null;
let previousQueueState = null;
let lastSMSSentTo = null;
let smsInProgress = false;
let isUpdating = false;

console.log('🚀 LITODA Queue System Starting...');

// ================================
// SEND SMS TO NEXT DRIVER
// ================================
function sendNextDriverSMS(driver) {
  if (smsInProgress) {
    console.log('⏳ SMS already in progress, skipping...');
    return;
  }

  if (lastSMSSentTo === driver.id) {
    console.log('✅ SMS already sent to driver:', driver.id);
    return;
  }

  smsInProgress = true;
  console.log('📤 Sending SMS to driver:', driver.id);

  $.ajax({
    url: '../../api/auth/send_sms.php',
    type: 'POST',
    data: {
      driver_id: driver.id,
      driver_name: driver.firstname + " " + driver.lastname,
      tricycle_number: driver.tricycle_number,
      contact_no: driver.contact_no,
      queue_number: driver.queue_number
    },
    dataType: 'json',
    timeout: 10000,
    success: function(response) {
      console.log("📩 SMS Response:", response);
      
      if (response.success) {
        lastSMSSentTo = driver.id;
        showNotification(`📩 SMS sent to ${driver.firstname} ${driver.lastname} (Queue #${driver.queue_number})`, 'success');
      } else if (response.duplicate) {
        console.log('⚠️ Duplicate SMS prevented by server');
        lastSMSSentTo = driver.id;
        console.log(`✓ ${driver.firstname} ${driver.lastname} already notified`);
      } else {
        console.error('❌ SMS Failed:', response.message);
        lastSMSSentTo = driver.id;
        showNotification(`⚠ SMS delivery pending`, 'info');
      }
    },
    error: function(xhr, status, error) {
      console.error("❌ SMS AJAX Error:", error, status);
      lastSMSSentTo = driver.id;
      if (status !== 'timeout' && status !== 'abort') {
        showNotification("⚠ SMS service temporarily unavailable", "error");
      }
    },
    complete: function() {
      smsInProgress = false;
    }
  });
}

// ================================
// DETECT CHANGES IN QUEUE
// ================================
function detectQueueChanges(currentData) {
  if (!previousQueueState) {
    previousQueueState = currentData;
    return { hasChanges: false, added: [], removed: [], orderChanged: false };
  }

  const prevIds = previousQueueState.map(d => d.id);
  const currIds = currentData.map(d => d.id);

  const added = currIds.filter(id => !prevIds.includes(id));
  const removed = prevIds.filter(id => !currIds.includes(id));
  
  const orderChanged = prevIds.length === currIds.length && 
                       prevIds.some((id, idx) => id !== currIds[idx]);

  const hasChanges = added.length > 0 || removed.length > 0 || orderChanged;

  if (hasChanges) {
    console.log('🔄 Queue changes detected:', { 
      added: added.length, 
      removed: removed.length,
      orderChanged 
    });
    
    // Show notification for driver added
    if (added.length > 0) {
      const newDriver = currentData.find(d => d.id === added[0]);
      if (newDriver) {
        const name = `${newDriver.firstname || ''} ${newDriver.lastname || ''}`.trim();
        showNotification(`✅ ${name} joined as Queue #${newDriver.queue_number}`, 'success');
      }
    } 
    
    // Driver removed/dispatched
    if (removed.length > 0) {
      const removedDriver = previousQueueState.find(d => d.id === removed[0]);
      console.log('🚗 Queue #' + (removedDriver?.queue_number || '?') + ' was dispatched');
      lastSMSSentTo = null;
      showNotification(`🚗 Queue #${removedDriver?.queue_number || '?'} dispatched - Next driver notified`, 'info');
    }
  }

  previousQueueState = JSON.parse(JSON.stringify(currentData));
  return { hasChanges, added, removed, orderChanged };
}

// ================================
// LOAD QUEUE DATA (NO PAGE RELOAD)
// ================================
function loadQueueData() {
  if (isUpdating) {
    console.log('⏸️ Update already in progress, skipping...');
    return;
  }

  isUpdating = true;
  console.log('🔄 Fetching queue data...');
  
  $.ajax({
    url: '../../api/auth/LQ.php',
    type: 'GET',
    data: { action: 'fetch' },
    dataType: 'json',
    cache: false,
    success: function(response) {
      console.log('✅ Queue data received:', response.data?.length || 0, 'drivers');
      
      if (response.success && response.data && response.data.length > 0) {
        const changes = detectQueueChanges(response.data);
        const onqueueDrivers = response.data.filter(d => d.status === 'Onqueue');

        if (onqueueDrivers.length > 0) {
          const servingDriver = onqueueDrivers[0];
          updateServingSection(servingDriver);

          const remainingDrivers = onqueueDrivers.slice(1);
          if (remainingDrivers.length > 0) {
            const nextDriver = remainingDrivers[0];
            
            // Send SMS ONLY when driver dispatched
            if (nextDriver && nextDriver.contact_no && 
                changes.hasChanges && changes.removed.length > 0) {
              
              if (lastSMSSentTo !== nextDriver.id) {
                console.log('🚗 Now Serving dispatched! Notifying Queue #' + nextDriver.queue_number);
                sendNextDriverSMS(nextDriver);
              }
            }

            updateQueueTable(remainingDrivers);
          } else {
            showEmptyQueue();
          }
        } else {
          showNoServing();
          showEmptyQueue();
        }
      } else {
        showNoServing();
        showEmptyQueue();
      }
    },
    error: function(xhr, status, error) {
      console.error('❌ Error loading queue:', error);
      if (status !== 'timeout') {
        showNotification('❌ Connection error. Retrying...', 'error');
      }
    },
    complete: function() {
      isUpdating = false;
    },
    timeout: 5000
  });
}

// ================================
// SHOW NOTIFICATION (ONLY ONE)
// ================================
function showNotification(message, type) {
  $('.sms-notification').remove();

  const icons = {
    success: 'check-circle',
    error: 'exclamation-circle',
    info: 'info-circle'
  };

  const notification = $('<div>', {
    class: `sms-notification sms-notification-${type}`,
    html: `
      <div style="display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-${icons[type] || 'info-circle'}" style="font-size: 20px;"></i>
        <span>${message}</span>
      </div>
    `
  });

  $('body').append(notification);
  setTimeout(() => notification.addClass('show'), 100);
  setTimeout(() => {
    notification.removeClass('show');
    setTimeout(() => notification.remove(), 300);
  }, 4000);
}

// ================================
// UPDATE NOW SERVING (WITH PERMANENT QUEUE NUMBER)
// ================================
function updateServingSection(driver) {
  const profileImg = driver.profile_pic ? '../../' + driver.profile_pic : '../../assets/img/default-profile.png';
  const driverName = `${driver.firstname || ''} ${driver.lastname || ''}`.trim();
  const tricycleNo = driver.tricycle_number || driver.tricycle_no || 'N/A';
  const queueNum = driver.queue_number || '?';

  const servingHTML = `
    <h2 class="now-serving-title">Now Serving</h2>
    <div class="serving-cards-container">
      <div class="serving-card">
        <div class="queue-number-badge">${queueNum}</div>
        <img src="${profileImg}" alt="Profile" class="serving-profile" 
             onerror="this.src='../../assets/img/default-profile.png'">
        <div class="serving-info">
          <div class="serving-name">${driverName}</div>
          <div class="serving-tricycle">${tricycleNo}</div>
        </div>
      </div>
    </div>
  `;

  const currentHTML = $('.now-serving-section').html();
  if (currentHTML !== servingHTML) {
    $('.now-serving-section').html(servingHTML);
  }
}

function showNoServing() {
  const noServingHTML = `
    <h2 class="now-serving-title">Now Serving</h2>
    <div class="no-serving"></div>
  `;
  
  const currentHTML = $('.now-serving-section').html();
  if (currentHTML !== noServingHTML) {
    $('.now-serving-section').html(noServingHTML);
  }
}

function formatQueueTime(queuedAt) {
  if (!queuedAt) return 'N/A';
  const date = new Date(queuedAt);
  let hours = date.getHours();
  const minutes = date.getMinutes();
  const ampm = hours >= 12 ? 'PM' : 'AM';
  hours = hours % 12 || 12;
  const minutesStr = minutes < 10 ? '0' + minutes : minutes;
  return `${hours}:${minutesStr} ${ampm}`;
}

// ================================
// UPDATE QUEUE TABLE (WITH PERMANENT NUMBERS)
// ================================
function updateQueueTable(drivers) {
  let html = '';
  
  drivers.forEach((driver, index) => {
    const queueNumber = driver.queue_number || '?';
    const profileImg = driver.profile_pic ? '../../' + driver.profile_pic : '../../assets/img/default-profile.png';
    const driverName = `${driver.firstname || ''} ${driver.lastname || ''}`.trim();
    const tricycleNo = driver.tricycle_number || driver.tricycle_no || 'N/A';
    const inQueueTime = formatQueueTime(driver.queued_at);
    const nextClass = index === 0 ? ' next-in-line' : '';
    const nextBadge = index === 0 ? ' <span class="next-badge">NEXT</span>' : '';

    html += `
      <tr class="${nextClass}" data-driver-id="${driver.id}">
        <td class="queue-number-cell">${queueNumber}</td>
        <td><img src="${profileImg}" alt="Profile" class="driver-pic" 
             onerror="this.src='../../assets/img/default-profile.png'"></td>
        <td class="driver-name-cell">${driverName}${nextBadge}</td>
        <td>${tricycleNo}</td>
        <td>${inQueueTime}</td>
        <td><span class="status-badge status-waiting">Waiting</span></td>
      </tr>
    `;
  });

  const finalHTML = html || '<tr><td colspan="6" class="no-records">No drivers waiting in queue</td></tr>';
  
  const currentHTML = $('#queue-body').html();
  if (currentHTML !== finalHTML) {
    $('#queue-body').html(finalHTML);
  }
}

function showEmptyQueue() {
  const emptyHTML = '<tr><td colspan="6" class="no-records">No drivers waiting in queue</td></tr>';
  const currentHTML = $('#queue-body').html();
  if (currentHTML !== emptyHTML) {
    $('#queue-body').html(emptyHTML);
  }
}

// ================================
// INITIALIZE
// ================================
$(document).ready(function() {
  console.log('✅ Document ready - Starting queue system');
  console.log('🔄 Loading initial queue data...');
  
  loadQueueData();
  
  setInterval(function() {
    loadQueueData();
  }, 3000);
  
  $(window).on('focus', function() {
    console.log('👁️ Window focused - refreshing data');
    loadQueueData();
  });
  
  console.log('📱 Real-time updates: ACTIVE');
  console.log('⏱️ Refresh interval: 3 seconds');
  console.log('📩 SMS System: PhilSMS Integrated');
  console.log('🔢 Queue Numbers: PERMANENT');
});