<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../../database/db.php';

// Admin ID from session
$adminId = $_SESSION['admin_id'] ?? null;

// Default admin info
$admin = [
    'firstname'   => '',
    'lastname'    => '',
    'username'    => '',
    'profile_pic' => 'uploads/default.png'
];

if ($adminId) {
    // Fetch admin info from database
    $stmt = $conn->prepare("SELECT firstname, lastname, username, profile_pic FROM admins WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $resultObj = $stmt->get_result();

        if ($resultObj && $resultObj->num_rows > 0) {
            $row = $resultObj->fetch_assoc();
            if ($row) {
                $admin['firstname']   = $row['firstname'] ?? '';
                $admin['lastname']    = $row['lastname'] ?? '';
                $admin['username']    = $row['username'] ?? '';
                $admin['profile_pic'] = $row['profile_pic'] ?: 'uploads/default.png';
            }
        }
        $stmt->close();
    }
}

// Full path to profile picture
$profilePicPath = "../../assets/" . ltrim($admin['profile_pic'], '/');
if (!file_exists($profilePicPath)) {
    $profilePicPath = "../../assets/uploads/default.png";
}

// Current page filename
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<nav class="navbar">
  <div class="navbar-container">
    <!-- Logo -->
    <div class="navbar-logo">
      <img src="../../assets/images/logo1.png" alt="LITODA" class="logo-img">
      <span class="logo-text">LITODA</span>
    </div>

    <!-- Navigation Links -->
    <ul class="navbar-links">
      <li><a href="../../pages/dashboard/dashboard.php" class="<?php echo ($currentPage == 'dashboard.php') ? 'active' : ''; ?>">Dashboard</a></li>
      <li><a href="../../pages/manage-drivers/managedrivers.php" class="<?php echo ($currentPage == 'managedrivers.php') ? 'active' : ''; ?>">Manage Drivers</a></li>
      <li><a href="../../pages/queue-management/qm.php" class="<?php echo ($currentPage == 'qm.php') ? 'active' : ''; ?>">Queue Management</a></li>
      <li><a href="../../pages/reports/reports.php" class="<?php echo ($currentPage == 'reports.php') ? 'active' : ''; ?>">Reports</a></li>
      <li><a href="#" onclick="openLoginLogsModal(); return false;" class="login-logs-link">Login Logs</a></li>
    </ul>

    <!-- Profile Section -->
    <div class="navbar-profile">
      <img src="<?php echo htmlspecialchars($profilePicPath); ?>" class="profile-pic" id="profileDropdownBtn">
      <div class="dropdown-menu" id="dropdownMenu">
        <div class="dropdown-header">
          <img src="<?php echo htmlspecialchars($profilePicPath); ?>" class="dropdown-avatar">
          <div>
            <p class="dropdown-name"><?php echo htmlspecialchars($admin['firstname'] . ' ' . $admin['lastname']); ?></p>
            <p class="dropdown-role">Admin</p>
          </div>
        </div>
        <hr>
        <button id="openProfileBtn" class="dropdown-btn">My Profile</button>
        <a href="../../pages/login/login.php" class="dropdown-btn logout">Logout</a>
      </div>
    </div>
  </div>
</nav>

<!-- Profile Modal -->
<div class="modal" id="profileModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>My Profile</h2>
      <button class="close-btn" id="closeProfileModal">&times;</button>
    </div>

    <form id="profileForm" enctype="multipart/form-data">
      <!-- Profile Picture Section -->
      <div class="profile-picture-section">
        <label class="form-label">Profile Picture *</label>
        <div class="profile-picture-container" id="adminProfilePictureContainer" onclick="document.getElementById('adminFileInput').click()">
          <img src="<?php echo htmlspecialchars($profilePicPath); ?>" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">
        </div>
        <input type="file" id="adminFileInput" name="profile_pic" accept="image/*" style="display: none;">
        <div id="adminStatusMessage" class="status-message" style="display: none;"></div>
      </div>

      <!-- First & Middle Name Row -->
      <div class="inputSection">
        <div class="colInputSection">
          <label for="admin_firstname" class="form-label">First Name *</label>
          <input type="text" id="admin_firstname" name="firstname" value="<?php echo htmlspecialchars($admin['firstname']); ?>" placeholder="First Name" required>
        </div>
        <div class="colInputSection">
          <label for="admin_middlename" class="form-label">Middle Initial</label>
          <input type="text" id="admin_middlename" name="middlename" placeholder="Middle Initial">
        </div>
      </div>

      <!-- Last Name & Username Row -->
      <div class="inputSection">
        <div class="colInputSection">
          <label for="admin_lastname" class="form-label">Last Name *</label>
          <input type="text" id="admin_lastname" name="lastname" value="<?php echo htmlspecialchars($admin['lastname']); ?>" placeholder="Last Name" required>
        </div>
        <div class="colInputSection">
          <label for="admin_username" class="form-label">Username *</label>
          <input type="text" id="admin_username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" placeholder="Username" required>
        </div>
      </div>

      <!-- Password Row -->
      <div class="inputSection">
        <div class="colInputSection">
          <label for="admin_password" class="form-label">New Password</label>
          <input type="password" id="admin_password" name="password" placeholder="Enter new password (Optional)">
        </div>
      </div>

      <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($admin['profile_pic']); ?>">

      <button type="submit" class="submit-btn" id="adminSaveBtn" disabled>Save Changes</button>
    </form>
  </div>
</div>

<!-- Success Modal -->
<div class="success-modal" id="successModal">
  <div class="success-modal-content">
    <h3>✓ Success!</h3>
    <p>Profile saved successfully</p>
  </div>
</div>

<script>
const dropdownBtn = document.getElementById('profileDropdownBtn');
const dropdownMenu = document.getElementById('dropdownMenu');
const profileModal = document.getElementById('profileModal');
const openProfileBtn = document.getElementById('openProfileBtn');
const closeProfileModal = document.getElementById('closeProfileModal');
const adminSaveBtn = document.getElementById('adminSaveBtn');
const successModal = document.getElementById('successModal');
const adminFileInput = document.getElementById('adminFileInput');
const adminProfilePictureContainer = document.getElementById('adminProfilePictureContainer');
const adminStatusMessage = document.getElementById('adminStatusMessage');

// Dropdown controls
dropdownBtn.onclick = () => dropdownMenu.classList.toggle('show');
openProfileBtn.onclick = () => {
  dropdownMenu.classList.remove('show');
  profileModal.classList.add('show');
};
closeProfileModal.onclick = () => profileModal.classList.remove('show');

window.onclick = e => { 
  if (e.target === profileModal) profileModal.classList.remove('show');
  if (!e.target.matches('.profile-pic')) {
    if (dropdownMenu.classList.contains('show')) {
      dropdownMenu.classList.remove('show');
    }
  }
};

// Function to open login logs modal (will be called from dashboard)
function openLoginLogsModal() {
  // Check if we're on the dashboard page
  if (typeof openModal === 'function') {
    openModal();
  } else {
    // Redirect to dashboard if not on dashboard page
    window.location.href = '../../pages/dashboard/dashboard.php?openLogs=true';
  }
}

// File upload preview
if (adminFileInput) {
  adminFileInput.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
      // Validate file type
      if (!file.type.startsWith('image/')) {
        showAdminStatus('Please select an image file', 'error');
        this.value = '';
        return;
      }
      
      // Validate file size (max 5MB)
      if (file.size > 5 * 1024 * 1024) {
        showAdminStatus('Image size must be less than 5MB', 'error');
        this.value = '';
        return;
      }
      
      const reader = new FileReader();
      reader.onload = function(event) {
        adminProfilePictureContainer.innerHTML = `<img src="${event.target.result}" alt="Profile Picture" style="width: 100%; height: 100%; object-fit: cover; border-radius: 10px;">`;
        showAdminStatus('Image selected successfully!', 'success');
      };
      reader.readAsDataURL(file);
    }
  });
}

function showAdminStatus(message, type) {
  if (adminStatusMessage) {
    adminStatusMessage.textContent = message;
    adminStatusMessage.className = `status-message status-${type}`;
    adminStatusMessage.style.display = 'block';
    setTimeout(() => {
      adminStatusMessage.style.display = 'none';
    }, 3000);
  }
}

// Form validation
const requiredFields = ['admin_firstname', 'admin_lastname', 'admin_username'];

function validateAdminForm() {
  const allFilled = requiredFields.every(fieldId => {
    const field = document.getElementById(fieldId);
    return field && field.value.trim() !== '';
  });
  
  if (allFilled) {
    adminSaveBtn.disabled = false;
  } else {
    adminSaveBtn.disabled = true;
  }
}

requiredFields.forEach(fieldId => {
  const field = document.getElementById(fieldId);
  if (field) {
    field.addEventListener('input', validateAdminForm);
  }
});

validateAdminForm();

// Save Profile Changes
document.getElementById('profileForm').onsubmit = async (e) => {
  e.preventDefault();
  
  if (adminSaveBtn.disabled) {
    return;
  }
  
  const formData = new FormData(e.target);
  try {
    const res = await fetch('../../api/auth/update_profile.php', { method: 'POST', body: formData });
    const data = await res.json();
    
    if (data.success) {
      profileModal.classList.remove('show');
      successModal.style.display = 'flex';
      
      setTimeout(() => {
        successModal.style.display = 'none';
        location.reload();
      }, 2000);
    } else {
      showAdminStatus(data.message || 'Failed to update profile', 'error');
    }
  } catch (err) {
    showAdminStatus("Server Error: " + err.message, 'error');
  }
};
</script>

<style>
  
/* === Navbar Layout === */
.navbar {
  background: #fff;
  padding: 10px 25px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 3px 6px rgba(0,0,0,0.1);
  position: relative;
  z-index: 100;
}

.navbar-container {
  display: flex;
  width: 100%;
  justify-content: space-between;
  align-items: center;
}

.logo-img { 
  height: 50px; 
  margin-right: 5px; 
}

.logo-text { 
  font-weight: 700; 
  font-size: 20px;
}

.navbar-logo { 
  display: flex; 
  align-items: center; 
}

.navbar-links { 
  list-style: none; 
  display: flex; 
  gap: 25px; 
  margin: 0; 
  padding: 0; 
}

.navbar-links a {
  color: #333;
  text-decoration: none;
  font-weight: 500;
  position: relative;
  transition: color 0.3s ease;
}

.navbar-links a::after {
  content: "";
  position: absolute;
  width: 0;
  height: 2px;
  bottom: -4px;
  left: 0;
  background-color: #10b981;
  transition: width 0.3s ease;
}

.navbar-links a:hover::after,
.navbar-links a.active::after {
  width: 100%;
}

.navbar-links a.active {
  color: #333;
  font-weight: 600;
}

/* Special styling for View Login Logs link */
.navbar-links a.login-logs-link {
  color: #333;
  font-weight: 500;
}

.navbar-links a.login-logs-link:hover {
  color: #333;
}

/* === Profile Dropdown === */
.navbar-profile { 
  position: relative; 
}

.profile-pic {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  cursor: pointer;
  object-fit: cover;
}

.dropdown-menu {
  display: none;
  position: absolute;
  right: 0;
  top: 55px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.15);
  min-width: 220px;
  overflow: hidden;
  z-index: 1000;
}

.dropdown-menu.show { 
  display: block; 
}

.dropdown-header {
  display: flex;
  align-items: center;
  padding: 15px;
  gap: 10px;
}

.dropdown-avatar {
  width: 45px;
  height: 45px;
  border-radius: 50%;
  object-fit: cover;
}

.dropdown-name { 
  font-weight: 600; 
  font-size: 15px; 
  margin: 0; 
}

.dropdown-role { 
  font-size: 13px; 
  color: #666; 
  margin: 0; 
}

.dropdown-btn {
  display: block;
  width: 100%;
  padding: 12px 18px;
  text-align: left;
  border: none;
  background: none;
  font-size: 15px;
  color: #333;
  cursor: pointer;
  text-decoration: none;
}

.dropdown-btn:hover {
  background: #f5f5f5;
}

/* === Profile Modal === */
#profileModal.modal {
  position: fixed;
  z-index: 999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.4);
  display: none;
  justify-content: center;
  align-items: center;
  overflow-y: auto;
}

#profileModal.modal.show {
  display: flex;
}

#profileModal .modal-content {
  background: #fff;
  border-radius: 16px;
  padding: 30px;
  width: 500px;
  max-width: 90%;
  box-shadow: 0 10px 30px rgba(0,0,0,0.2);
  margin: 20px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.modal-header h2 {
  color: #10b981;
  margin: 0;
}

.close-btn {
  background: none;
  border: none;
  font-size: 22px;
  cursor: pointer;
  color: #555;
}

.form-label {
  font-size: 14px;
  margin-bottom: 5px;
  display: block;
  color: #333;
}

.inputSection {
  display: flex;
  gap: 10px;
  margin-bottom: 10px;
}

.colInputSection {
  flex: 1;
}

.colInputSection input {
  width: 100%;
  padding: 12px 14px;
  border: 2px solid #e8f5e8;
  border-radius: 10px;
  font-size: 15px;
  outline: none;
  box-sizing: border-box;
}

.colInputSection input:focus {
  border-color: #10b981;
}

/* Profile Picture Section */
.profile-picture-section {
  margin: 10px 0;
}

.profile-picture-container {
  position: relative;
  width: 100px;
  height: 100px;
  border: 2px dashed #10b981;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  overflow: hidden;
}

.submit-btn {
  width: 100%;
  background: #10b981;
  color: white;
  border: none;
  padding: 14px;
  border-radius: 12px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  margin-top: 10px;
}

.submit-btn:disabled {
  background: #ccc;
  cursor: not-allowed;
}

/* Status Messages */
.status-message {
  padding: 10px;
  border-radius: 8px;
  margin: 10px 0;
  font-size: 14px;
}

.status-success {
  background: #d1fae5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.status-error {
  background: #fee2e2;
  color: #991b1b;
  border: 1px solid #fecaca;
}

/* Success Modal */
.success-modal {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 1100;
  background: rgba(0, 0, 0, 0.5);
  justify-content: center;
  align-items: center;
}

.success-modal-content {
  background: #fff;
  padding: 40px;
  border-radius: 10px;
  text-align: center;
  box-shadow: 0 8px 25px rgba(0,0,0,0.25);
  margin: 20px;
}

.success-modal-content h3 {
  color: #10b981;
  font-size: 1.5rem;
  margin-bottom: 15px;
  margin-top: 0;
}

.success-modal-content p {
  color: #666;
  font-size: 1rem;
  margin: 0;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes fadeInModal {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* ========================================
   RESPONSIVE DESIGN - MOBILE & TABLET
   ======================================== */

/* Tablet (Portrait) - 768px and below */
@media screen and (max-width: 768px) {
  .navbar {
    padding: 10px 15px;
  }

  .logo-img {
    height: 40px;
  }

  .logo-text {
    font-size: 18px;
  }

  .navbar-links {
    gap: 15px;
  }

  .navbar-links a {
    font-size: 14px;
  }

  .profile-pic {
    width: 40px;
    height: 40px;
  }

  .dropdown-menu {
    min-width: 200px;
    top: 50px;
  }

  #profileModal .modal-content {
    padding: 20px;
    width: 90%;
  }

  .modal-header h2 {
    font-size: 1.3rem;
  }

  .inputSection {
    flex-direction: column;
    gap: 10px;
  }
}

/* Mobile (Landscape and Portrait) - 600px and below */
@media screen and (max-width: 600px) {
  .navbar {
    padding: 8px 10px;
  }

  .logo-img {
    height: 35px;
    margin-right: 3px;
  }

  .logo-text {
    font-size: 16px;
  }

  .navbar-links {
    gap: 10px;
  }

  .navbar-links a {
    font-size: 12px;
    padding: 5px;
  }

  .navbar-links a::after {
    height: 1px;
    bottom: -2px;
  }

  .profile-pic {
    width: 35px;
    height: 35px;
  }

  .dropdown-menu {
    min-width: 180px;
    top: 45px;
    right: -10px;
  }

  .dropdown-header {
    padding: 12px;
  }

  .dropdown-avatar {
    width: 35px;
    height: 35px;
  }

  .dropdown-name {
    font-size: 13px;
  }

  .dropdown-role {
    font-size: 11px;
  }

  .dropdown-btn {
    padding: 10px 15px;
    font-size: 13px;
  }

  #profileModal .modal-content {
    padding: 20px 15px;
    width: 95%;
    margin: 10px;
  }

  .modal-header h2 {
    font-size: 1.2rem;
  }

  .close-btn {
    font-size: 20px;
  }

  .form-label {
    font-size: 13px;
  }

  .colInputSection input {
    padding: 10px 12px;
    font-size: 14px;
  }

  .submit-btn {
    padding: 12px;
    font-size: 14px;
  }

  .success-modal-content {
    padding: 30px 20px;
    width: 90%;
    margin: 15px;
  }

  .success-modal-content h3 {
    font-size: 1.3rem;
  }

  .success-modal-content p {
    font-size: 0.9rem;
  }
}

/* Extra Small Mobile - 400px and below */
@media screen and (max-width: 400px) {
  .navbar {
    padding: 8px;
  }

  .logo-img {
    height: 30px;
  }

  .logo-text {
    font-size: 14px;
  }

  .navbar-links {
    gap: 8px;
  }

  .navbar-links a {
    font-size: 11px;
    padding: 3px;
  }

  .profile-pic {
    width: 32px;
    height: 32px;
  }

  .dropdown-menu {
    min-width: 160px;
    right: -5px;
  }

  .dropdown-header {
    padding: 10px;
    gap: 8px;
  }

  .dropdown-avatar {
    width: 30px;
    height: 30px;
  }

  .dropdown-name {
    font-size: 12px;
  }

  .dropdown-role {
    font-size: 10px;
  }

  .dropdown-btn {
    padding: 8px 12px;
    font-size: 12px;
  }

  #profileModal .modal-content {
    padding: 15px;
    width: 98%;
  }

  .modal-header h2 {
    font-size: 1.1rem;
  }

  .profile-picture-container {
    width: 80px;
    height: 80px;
  }

  .status-message {
    font-size: 12px;
    padding: 8px;
  }
}

/* Landscape orientation specific fixes */
@media screen and (max-height: 500px) and (orientation: landscape) {
  #profileModal .modal-content {
    max-height: 90vh;
    overflow-y: auto;
  }

  .navbar {
    padding: 5px 15px;
  }

  .logo-img {
    height: 35px;
  }
}
</style>