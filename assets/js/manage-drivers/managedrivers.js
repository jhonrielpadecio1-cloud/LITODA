// Modal elements
const modal = document.getElementById("userModal");
const openBtn = document.getElementById("openModal");
const closeBtn = document.getElementById("closeModal");

// Camera modal elements
const cameraModal = document.getElementById("cameraModal");
const profilePictureContainer = document.getElementById("profilePictureContainer");
const closeCameraBtn = document.getElementById("closeCameraModal");
const cancelCameraBtn = document.getElementById("cancelCameraBtn");

// Camera elements
const video = document.getElementById("cameraVideo");
const canvas = document.getElementById("capturedCanvas");
const captureBtn = document.getElementById("captureBtn");
const retakeBtn = document.getElementById("retakeBtn");
const confirmBtn = document.getElementById("confirmBtn");

// Form elements
const submitBtn = document.getElementById("submitBtn");
const profileImageData = document.getElementById("profileImageData");
const statusMessage = document.getElementById("statusMessage");
const userForm = document.getElementById("userForm");

let stream = null;
let capturedImageData = null;

// Main modal controls
openBtn.onclick = () => modal.classList.add("show");
closeBtn.onclick = () => {
    modal.classList.remove("show");
    resetForm();
};
window.onclick = (e) => { 
    if (e.target === modal) {
        modal.classList.remove("show");
        resetForm();
    }
    if (e.target === cameraModal) {
        closeCameraModal();
    }
};

// Profile picture click to open camera
if (profilePictureContainer) {
    profilePictureContainer.onclick = openCamera;
}

// Camera modal controls
if (closeCameraBtn) closeCameraBtn.onclick = closeCameraModal;
if (cancelCameraBtn) cancelCameraBtn.onclick = closeCameraModal;
if (captureBtn) captureBtn.onclick = capturePhoto;
if (retakeBtn) retakeBtn.onclick = retakePhoto;
if (confirmBtn) confirmBtn.onclick = confirmPhoto;

// Camera functions
async function openCamera() {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            } 
        });
        video.srcObject = stream;
        cameraModal.classList.add("show");
        
        // Reset camera state
        video.style.display = 'block';
        canvas.style.display = 'none';
        captureBtn.style.display = 'inline-block';
        retakeBtn.style.display = 'none';
        confirmBtn.style.display = 'none';
        
    } catch (error) {
        console.error("Error accessing camera:", error);
        showStatus("Camera access denied or not available", "error");
    }
}

function closeCameraModal() {
    cameraModal.classList.remove("show");
    if (stream) {
        stream.getTracks().forEach(track => track.stop());
        stream = null;
    }
    video.srcObject = null;
}

async function capturePhoto() {
    const context = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    context.drawImage(video, 0, 0, canvas.width, canvas.height);
    const imageData = canvas.toDataURL('image/jpeg', 0.8);

    // Initial validation state
    showStatus("Validating face...", "info");
    captureBtn.disabled = true;
    captureBtn.textContent = "Validating...";

    try {
        // ============================
        // 1. VALIDATE SINGLE FACE
        // ============================
        const response = await fetch("http://127.0.0.1:5000/validate_single_face", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ image: imageData })
        });

        const result = await response.json();

        if (result.valid) {
            // ============================
            // 2. CHECK FOR DUPLICATE FACE
            // ============================
            showStatus("Checking for duplicate faces...", "info");

            const duplicateResponse = await fetch("http://127.0.0.1:5000/check_face_duplicate", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ image: imageData })
            });

            const duplicateResult = await duplicateResponse.json();

            if (duplicateResult.duplicate) {
                // Duplicate found
                showStatus(`Face already registered to: ${duplicateResult.matched_driver}`, "error");
                showGlobalStatus(
                    `This face is already registered to ${duplicateResult.matched_driver}. Please use a different person.`,
                    "error"
                );

                captureBtn.disabled = false;
                captureBtn.textContent = "Capture";
                return; // Stop further actions
            }

            // ============================
            // 3. FACE VALID + NO DUPLICATE
            // ============================
            video.style.display = "none";
            canvas.style.display = "block";

            captureBtn.style.display = "none";
            retakeBtn.style.display = "inline-block";
            confirmBtn.style.display = "inline-block";

            capturedImageData = imageData;

            showStatus("Face validated successfully!", "success");

        } else {
            // Invalid face capture
            showStatus(result.message || "Invalid face capture", "error");
            showGlobalStatus(result.message || "Invalid face capture", "error");

            captureBtn.disabled = false;
            captureBtn.textContent = "Capture";
        }

    } catch (err) {
        // ============================
        // 4. ERROR HANDLING
        // ============================
        console.error("Face validation error:", err);

        showStatus("Error validating face. Please try again.", "error");
        showGlobalStatus(
            "Cannot connect to face recognition system. Please ensure the Python server is running.",
            "error"
        );

        captureBtn.disabled = false;
        captureBtn.textContent = "Capture";
    }
}

function retakePhoto() {
    video.style.display = 'block';
    canvas.style.display = 'none';
    
    captureBtn.style.display = 'inline-block';
    captureBtn.disabled = false;
    captureBtn.textContent = "Capture";
    retakeBtn.style.display = 'none';
    confirmBtn.style.display = 'none';
    
    capturedImageData = null;
}

function confirmPhoto() {
    if (capturedImageData) {
        profilePictureContainer.innerHTML = `<img src="${capturedImageData}" alt="Profile Picture">`;
        profileImageData.value = capturedImageData;
        submitBtn.disabled = false;
        showStatus("Profile picture captured successfully!", "success");
        closeCameraModal();
    }
}

function showStatus(message, type) {
    if (statusMessage) {
        statusMessage.textContent = message;
        statusMessage.className = `status-message status-${type}`;
        statusMessage.style.display = 'block';
        
        if (type === 'success') {
            setTimeout(() => {
                statusMessage.style.display = 'none';
            }, 3000);
        }
    }
}

function resetForm() {
    if (userForm) userForm.reset();
    if (profilePictureContainer) {
        profilePictureContainer.innerHTML = `
            <div class="profile-picture-placeholder">
                <i class="fas fa-camera"></i>
                <span>Take Photo</span>
            </div>
        `;
    }
    if (profileImageData) profileImageData.value = '';
    if (submitBtn) submitBtn.disabled = true;
    if (statusMessage) statusMessage.style.display = 'none';
    capturedImageData = null;
}

// Form submission handling with contact validation (NO FACE DUPLICATE CHECK)
if (userForm) {
    userForm.onsubmit = function(e) {
        const contactInput = document.getElementById('contactnumber');

        if (!capturedImageData) {
            e.preventDefault();
            showStatus("Please take a profile picture before submitting", "error");
            return false;
        }

        // Only validate contact if it has a value
        if (contactInput && contactInput.value.length > 0 && contactInput.value.length !== 11) {
            e.preventDefault();
            showStatus("Contact number must be exactly 11 digits if provided", "error");
            contactInput.focus();
            return false;
        }

        return true;
    };
}

// Check for camera support
if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
    showStatus("Camera not supported on this device", "error");
    if (profilePictureContainer) {
        profilePictureContainer.style.cursor = 'not-allowed';
        profilePictureContainer.onclick = null;
    }
}

// Enhanced notification function
function showGlobalStatus(message, type) {
    const existing = document.querySelectorAll('.global-notification');
    existing.forEach(el => el.remove());
    
    const globalStatus = document.createElement('div');
    globalStatus.className = `global-notification status-${type}`;
    globalStatus.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px;">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}" 
               style="font-size: 20px;"></i>
            <span style="flex: 1;">${message}</span>
        </div>
    `;
    
    document.body.appendChild(globalStatus);
    
    setTimeout(() => {
        globalStatus.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        globalStatus.classList.remove('show');
        setTimeout(() => {
            if (document.body.contains(globalStatus)) {
                document.body.removeChild(globalStatus);
            }
        }, 300);
    }, 4000);
}

document.addEventListener('DOMContentLoaded', function() {
    // Add global notification styles
    if (!document.getElementById('global-notification-styles')) {
        const styles = document.createElement('style');
        styles.id = 'global-notification-styles';
        styles.textContent = `
            .global-notification {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
                min-width: 350px;
                max-width: 500px;
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: 0 8px 24px rgba(0,0,0,0.15);
                font-weight: 500;
                font-family: 'Poppins', sans-serif;
                opacity: 0;
                transform: translateX(400px);
                transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                backdrop-filter: blur(10px);
            }
            
            .global-notification.show {
                opacity: 1;
                transform: translateX(0);
            }
            
            .global-notification.status-success {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                color: white;
                border: 2px solid #059669;
            }
            
            .global-notification.status-error {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                color: white;
                border: 2px solid #dc2626;
            }
            
            .global-notification.status-info {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
                border: 2px solid #2563eb;
            }
        `;
        document.head.appendChild(styles);
    }
    
    const urlParams = new URLSearchParams(window.location.search);
    const success = urlParams.get('success');
    const error = urlParams.get('error');
    
    if (success === 'user_added') {
        showGlobalStatus('Driver added successfully!', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (success === 'user_updated') {
        showGlobalStatus('Driver updated successfully!', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (success === 'user_deleted') {
        showGlobalStatus('Driver deleted successfully!', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
    } else if (error) {
        let errorMessage;
        
        if (error.startsWith('duplicate_driver')) {
            errorMessage = 'Driver with same name and plate number already exists';
        } else {
            switch(error) {
                case 'missing_fields':
                    errorMessage = 'Please fill in all required fields';
                    break;
                case 'database_error':
                    errorMessage = 'Database error occurred';
                    break;
                case 'file_upload_failed':
                    errorMessage = 'Failed to upload profile picture';
                    break;
                case 'invalid_image_data':
                    errorMessage = 'Invalid image data provided';
                    break;
                case 'no_image_provided':
                    errorMessage = 'Profile picture is required';
                    break;
                case 'invalid_contact':
                    errorMessage = 'Contact number must be exactly 11 digits';
                    break;
                case 'delete_failed':
                    errorMessage = 'Failed to delete driver';
                    break;
                case 'invalid_image_type':
                    errorMessage = 'Invalid image type';
                    break;
                case 'update_failed':
                    errorMessage = 'Failed to update driver';
                    break;
                case 'database_insert_failed':
                    errorMessage = 'Failed to add driver to database';
                    break;
                default:
                    errorMessage = 'An error occurred';
                    break;
            }
        }
        
        showGlobalStatus(errorMessage, 'error');
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Contact number validation for Add form
    const contactInput = document.getElementById('contactnumber');
    if (contactInput) {
        contactInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
            
            if (this.value.length === 11) {
                this.style.borderColor = '#10b981';
            } else if (this.value.length === 0) {
                this.style.borderColor = '#d1d5db';
            } else {
                this.style.borderColor = '#d1d5db';
            }
        });
        
        contactInput.addEventListener('blur', function() {
            // Only show error if there's a value but it's not 11 digits
            if (this.value.length > 0 && this.value.length !== 11) {
                showStatus('Contact number must be exactly 11 digits if provided', 'error');
                this.style.borderColor = '#ef4444';
            }
        });
    }
    
    // Contact number validation for Edit form - now optional
    const editContactInput = document.getElementById('edit_contact');
    if (editContactInput) {
        editContactInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            if (this.value.length > 11) {
                this.value = this.value.slice(0, 11);
            }
            
            if (this.value.length === 11) {
                this.style.borderColor = '#10b981';
            } else if (this.value.length === 0) {
                this.style.borderColor = '#d1d5db';
            } else {
                this.style.borderColor = '#d1d5db';
            }
        });
        
        editContactInput.addEventListener('blur', function() {
            // Only show error if there's a value but it's not 11 digits
            if (this.value.length > 0 && this.value.length !== 11) {
                showEditStatus('Contact number must be exactly 11 digits if provided', 'error');
                this.style.borderColor = '#ef4444';
            }
        });
    }
});

// Toggle action menu dropdown
function toggleActionMenu(event, button) {
    event.stopPropagation();
    
    const dropdown = button.nextElementSibling;
    const allDropdowns = document.querySelectorAll('.action-dropdown');
    
    allDropdowns.forEach(dd => {
        if (dd !== dropdown) {
            dd.classList.remove('show');
        }
    });
    
    const rect = button.getBoundingClientRect();
    dropdown.style.top = (rect.bottom + 5) + 'px';
    dropdown.style.left = (rect.left - 80) + 'px';
    
    dropdown.classList.toggle('show');
}

// Close all dropdowns when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('.action-menu-container')) {
        const allDropdowns = document.querySelectorAll('.action-dropdown');
        allDropdowns.forEach(dd => dd.classList.remove('show'));
    }
});

// Delete driver function
function deleteDriver(driverId) {
    Swal.fire({
        title: "Are you sure?",
        text: "You won't be able to revert this!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Yes, delete it!"
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Deleting...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch(`../../api/manage-drivers/deletedriver.php?id=${driverId}`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    
                    if (data.success) {
                        showGlobalStatus('Driver deleted successfully!', 'success');
                        setTimeout(() => {
                            window.location.href = window.location.pathname + '?success=user_deleted';
                        }, 1000);
                    } else {
                        showGlobalStatus(data.message || 'Failed to delete driver', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.close();
                    showGlobalStatus('An error occurred while deleting the driver', 'error');
                });
        }
    });
}

// Edit Modal elements
const editModal = document.getElementById("editModal");
const closeEditModal = document.getElementById("closeEditModal");
const editUserForm = document.getElementById("editUserForm");
const editSubmitBtn = document.getElementById("editSubmitBtn");

// Edit Camera modal elements
const editCameraModal = document.getElementById("editCameraModal");
const editProfilePictureContainer = document.getElementById("editProfilePictureContainer");
const closeEditCameraBtn = document.getElementById("closeEditCameraModal");
const cancelEditCameraBtn = document.getElementById("cancelEditCameraBtn");

// Edit Camera elements
const editVideo = document.getElementById("editCameraVideo");
const editCanvas = document.getElementById("editCapturedCanvas");
const editCaptureBtn = document.getElementById("editCaptureBtn");
const editRetakeBtn = document.getElementById("editRetakeBtn");
const editConfirmBtn = document.getElementById("editConfirmBtn");
const editProfileImageData = document.getElementById("editProfileImageData");
const editStatusMessage = document.getElementById("editStatusMessage");

let editStream = null;
let editCapturedImageData = null;

// Edit Modal Controls
if (closeEditModal) {
    closeEditModal.onclick = () => {
        editModal.classList.remove("show");
    };
}

window.addEventListener('click', (e) => {
    if (e.target === editModal) {
        editModal.classList.remove("show");
    }
    if (e.target === editCameraModal) {
        closeEditCameraModal();
    }
});

// Edit driver function
function editDriver(driverId) {
    console.log('Edit driver:', driverId);
    
    showEditStatus("Loading driver data...", "success");
    
    fetch(`../../api/manage-drivers/getuser.php?id=${driverId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                populateEditForm(data.data);
                editModal.classList.add("show");
            } else {
                showGlobalStatus(data.message || 'Failed to load driver data', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showGlobalStatus('An error occurred while loading driver data', 'error');
        });
}

// Populate edit form with driver data
function populateEditForm(driver) {
    document.getElementById('edit_driver_id').value = driver.id;
    document.getElementById('edit_firstname').value = driver.firstname;
    document.getElementById('edit_middlename').value = driver.middlename || '';
    document.getElementById('edit_lastname').value = driver.lastname;
    document.getElementById('edit_platenumber').value = driver.tricycle_number;
    document.getElementById('edit_contact').value = driver.contact_no || '';
    
    if (driver.profile_pic) {
        document.getElementById('existingImagePath').value = driver.profile_pic;
        editProfilePictureContainer.innerHTML = `<img src="../../${driver.profile_pic}" alt="Profile Picture">`;
    } else {
        editProfilePictureContainer.innerHTML = `
            <div class="profile-picture-placeholder">
                <i class="fa-solid fa-camera"></i>
                <span>Change Photo</span>
            </div>
        `;
    }
    
    editStatusMessage.style.display = 'none';
}

// Edit Profile picture click to open camera
if (editProfilePictureContainer) {
    editProfilePictureContainer.onclick = openEditCamera;
}

// Edit Camera modal controls
if (closeEditCameraBtn) closeEditCameraBtn.onclick = closeEditCameraModal;
if (cancelEditCameraBtn) cancelEditCameraBtn.onclick = closeEditCameraModal;
if (editCaptureBtn) editCaptureBtn.onclick = captureEditPhoto;
if (editRetakeBtn) editRetakeBtn.onclick = retakeEditPhoto;
if (editConfirmBtn) editConfirmBtn.onclick = confirmEditPhoto;

// Edit Camera functions
async function openEditCamera() {
    try {
        editStream = await navigator.mediaDevices.getUserMedia({ 
            video: { 
                width: { ideal: 640 },
                height: { ideal: 480 },
                facingMode: 'user'
            } 
        });
        editVideo.srcObject = editStream;
        editCameraModal.classList.add("show");
        
        editVideo.style.display = 'block';
        editCanvas.style.display = 'none';
        editCaptureBtn.style.display = 'inline-block';
        editRetakeBtn.style.display = 'none';
        editConfirmBtn.style.display = 'none';
        
    } catch (error) {
        console.error("Error accessing camera:", error);
        showEditStatus("Camera access denied or not available", "error");
    }
}

function closeEditCameraModal() {
    editCameraModal.classList.remove("show");
    if (editStream) {
        editStream.getTracks().forEach(track => track.stop());
        editStream = null;
    }
    editVideo.srcObject = null;
}

function closeEditCameraModal() {
    editCameraModal.classList.remove("show");
    if (editStream) {
        editStream.getTracks().forEach(track => track.stop());
        editStream = null;
    }
    editVideo.srcObject = null;
}

async function captureEditPhoto() {
    const context = editCanvas.getContext('2d');
    editCanvas.width = editVideo.videoWidth;
    editCanvas.height = editVideo.videoHeight;
    context.drawImage(editVideo, 0, 0, editCanvas.width, editCanvas.height);

    const imageData = editCanvas.toDataURL('image/jpeg', 0.85);

    // UI update
    showEditStatus("Validating face...", "info");
    setEditCaptureButtonState(true, "Validating...");

    try {
        // ------------------------------------------------
        // 1️⃣ VALIDATE SINGLE FACE
        // ------------------------------------------------
        const validateRes = await fetch("http://127.0.0.1:5000/validate_single_face", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ image: imageData })
        });

        const validateData = await validateRes.json();

        if (!validateData.valid) {
            handleEditError(validateData.message || "No valid face detected.");
            return;
        }

        // ------------------------------------------------
        // 2️⃣ CHECK IF SAME PERSON USING EXISTING PROFILE
        // ------------------------------------------------
        const existingPath = document.getElementById("existingImagePath").value;

        if (existingPath) {
            showEditStatus("Verifying if same person...", "info");

            const matchRes = await fetch("http://127.0.0.1:5000/check_face_match", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    existing_image_path: existingPath,
                    new_image: imageData
                })
            });

            const matchData = await matchRes.json();

            if (!matchData.same_face) {
                // ❌ INVALID – NOT SAME PERSON
                handleEditError("This is NOT the same registered driver!");
                showGlobalStatus("❌ Face mismatch. Update blocked.", "error");
                return;
            }

            // ✔ SAME PERSON → ACCEPT UPDATE
            editCapturedImageData = imageData;
            editVideo.style.display = "none";
            editCanvas.style.display = "block";

            editCaptureBtn.style.display = "none";
            editRetakeBtn.style.display = "inline-block";
            editConfirmBtn.style.display = "inline-block";

            showEditStatus("✓ Same person verified!", "success");
            showGlobalStatus("✓ Face match confirmed. You can update the profile.", "success");

        } else {
            // ------------------------------------------------
            // 3️⃣ NO EXISTING IMAGE → JUST ACCEPT
            // ------------------------------------------------
            editCapturedImageData = imageData;

            editVideo.style.display = "none";
            editCanvas.style.display = "block";

            editCaptureBtn.style.display = "none";
            editRetakeBtn.style.display = "inline-block";
            editConfirmBtn.style.display = "inline-block";

            showEditStatus("Face validated successfully!", "success");
        }

    } catch (error) {
        console.error("Edit Photo Validation Error:", error);
        handleEditError("Cannot connect to face recognition server.");
    } finally {
        setEditCaptureButtonState(false, "Capture");
    }
}


// ---------------------------------------------------------
// FUNCTION: Handle errors
// ---------------------------------------------------------
function handleEditError(message) {
    showEditStatus(message, "error");
    showGlobalStatus(message, "error");

    // Reset UI
    editCanvas.style.display = "none";
    editVideo.style.display = "block";

    editCaptureBtn.style.display = "inline-block";
    editRetakeBtn.style.display = "none";
    editConfirmBtn.style.display = "none";

    editCapturedImageData = null;

    setEditCaptureButtonState(false, "Capture");
}


// ---------------------------------------------------------
// FUNCTION: Retake Photo
// ---------------------------------------------------------
function retakeEditPhoto() {
    editVideo.style.display = "block";
    editCanvas.style.display = "none";

    editCapturedImageData = null;

    editCaptureBtn.style.display = "inline-block";
    editCaptureBtn.disabled = false;
    editCaptureBtn.textContent = "Capture";

    editRetakeBtn.style.display = "none";
    editConfirmBtn.style.display = "none";
}


// ---------------------------------------------------------
// FUNCTION: Confirm Photo
// ---------------------------------------------------------
function confirmEditPhoto() {
    if (!editCapturedImageData) return;

    editProfilePictureContainer.innerHTML =
        `<img src="${editCapturedImageData}" 
              alt="New Profile Picture" 
              style="width:150px; height:150px; object-fit:cover; border-radius:50%; cursor:pointer;">`;

    editProfileImageData.value = editCapturedImageData;

    if (editSubmitBtn) editSubmitBtn.disabled = false;

    showEditStatus("Profile picture updated!", "success");
    showGlobalStatus("✓ New photo applied. Click 'Update User' to save.", "success");

    closeEditCameraModal();
}


// ---------------------------------------------------------
// FUNCTION: Button state helper
// ---------------------------------------------------------
function setEditCaptureButtonState(isDisabled, text) {
    editCaptureBtn.disabled = isDisabled;
    editCaptureBtn.textContent = text;
}


// ---------------------------------------------------------
// FUNCTION: Status Message
// ---------------------------------------------------------
function showEditStatus(message, type) {
    if (editStatusMessage) {
        editStatusMessage.textContent = message;
        editStatusMessage.className = `status-message status-${type}`;
        editStatusMessage.style.display = "block";

        if (type === "success") {
            setTimeout(() => {
                editStatusMessage.style.display = "none";
            }, 2500);
        }
    }
}


// ---------------------------------------------------------
// Edit form submission validation
// ---------------------------------------------------------
if (editUserForm) {
    editUserForm.onsubmit = function(e) {
        const editContactInput = document.getElementById('edit_contact');

        if (editContactInput && editContactInput.value.length > 0 &&
            editContactInput.value.length !== 11) {

            e.preventDefault();
            showEditStatus("Contact number must be 11 digits.", "error");
            editContactInput.focus();
            return false;
        }
        return true;
    };
}
