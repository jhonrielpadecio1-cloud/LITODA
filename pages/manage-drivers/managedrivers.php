    <?php
    require_once '../../api/auth/auth_guard.php';
    include '../../database/db.php';

    // Pagination setup
    $limit = 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Search handling - removed email from search
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $whereClause = '';
    if (!empty($search)) {
        $searchEscaped = $conn->real_escape_string($search);
        $whereClause = "WHERE firstname LIKE '%$searchEscaped%' 
                        OR middlename LIKE '%$searchEscaped%' 
                        OR lastname LIKE '%$searchEscaped%'
                        OR tricycle_number LIKE '%$searchEscaped%'
                        OR contact_no LIKE '%$searchEscaped%'";
    }

    // Count total records
    $totalQuery = $conn->query("SELECT COUNT(*) as total FROM drivers $whereClause");
    $totalRow = $totalQuery->fetch_assoc();
    $totalRecords = $totalRow['total'];
    $totalPages = ceil($totalRecords / $limit);

    // Fetch limited data
    $sql = "SELECT * FROM drivers $whereClause ORDER BY registered_at DESC LIMIT $limit OFFSET $offset";
    $result = $conn->query($sql);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Manage Drivers - LITODA</title>
        <link rel="stylesheet" href="../../assets/css/navbar/navbar.css">
        <link rel="stylesheet" href="../../assets/css/manage-drivers/managedriver.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    </head>
    <body>
        <?php include('../../assets/components/navbar.php'); ?>
        <div class="container">
            <div class="page-label">
            </div>
            <div class="row">
                <div class="search-section">
                    <form action="managedrivers.php" method="get">
                        <input type="text" class="search-input" placeholder="Search..." name="search" value="<?= htmlspecialchars($search) ?>">
                        <button class="page-btn" type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </form>
                </div>
                <div class="add-drivers-section">
                    <button class="page-btn" id="openModal"><i class="fa-solid fa-user-plus"></i> Register Driver</button>
                </div>
            </div>

            <table>
                <tr>
                    <th>Profile</th>
                    <th>Name</th>
                    <th>Plate Number</th>
                    <th>Contact Number</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php
                                    $profile_pic_path = !empty($row['profile_pic']) ? '../../' . $row['profile_pic'] : '';
                                    if(!empty($profile_pic_path) && file_exists($profile_pic_path) && is_file($profile_pic_path)): ?>
                                        <img src="<?= htmlspecialchars($profile_pic_path) ?>" 
                                            alt="<?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?>" 
                                            style="width:50px; height:50px; border-radius:50%; object-fit:cover; border:2px solid #e5e7eb;">
                                    <?php else: ?>
                                        <div style="width:50px; height:50px; background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; font-size:18px;">
                                            <?= strtoupper(substr($row['firstname'],0,1) . substr($row['lastname'],0,1)) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['firstname'].' '.$row['middlename'].' '.$row['lastname']) ?></td>
                                <td><?= htmlspecialchars($row['tricycle_number']) ?></td>
                                <td>
                                <?= !empty($row['contact_no']) ? htmlspecialchars($row['contact_no']) : "<span style='color:#9ca3af;'>NONE</span>" ?></td>

                                <td><?= htmlspecialchars($row['registered_at']) ?></td>
                                <td>
                                    <div class="action-menu-container">
                                        <button class="action-menu-btn" onclick="toggleActionMenu(event, this)">
                                            <i class="fa-solid fa-ellipsis-vertical"></i>
                                        </button>
                                        <div class="action-dropdown">
                                            <button class="dropdown-item edit-btn" onclick="editDriver(<?= $row['id'] ?>)">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </button>
                                            <button class="dropdown-item delete-btn" onclick="deleteDriver(<?= $row['id'] ?>)">
                                                <i class="fa-solid fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center;">No drivers found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($totalPages > 1): ?>
                <div style="text-align:center; margin:20px;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>"><button class="page-btn">Previous</button></a>
                    <?php endif; ?>

                    <?php for ($i=1; $i<=$totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">
                            <button class="page-btn" style="<?= ($i==$page)?'background:#059669;':'' ?>"><?= $i ?></button>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>"><button class="page-btn">Next</button></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Add Driver Modal -->
        <div class="modal" id="userModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add Drivers</h2>
                    <button class="close-btn" id="closeModal">&times;</button>
                </div>
                <form id="userForm" method="post" action="../../api/manage-drivers/adduser.php" enctype="multipart/form-data">
                    <div class="profile-picture-section">
                        <label class="form-label">Profile Picture *</label>
                        <div class="profile-picture-container" id="profilePictureContainer">
                            <div class="profile-picture-placeholder">
                                <i class="fa-solid fa-camera"></i><span>Take Photo</span>
                            </div>
                        </div>
                        <div id="statusMessage" class="status-message" style="display:none;"></div>
                    </div>

                    <div class="inputSection">
                        <div class="colInputSection">
                            <label for="firstname" class="form-label">First Name*</label>
                            <input type="text" name="firstname" placeholder="First Name" required>
                        </div>
                        <div class="colInputSection">
                            <label for="middlename" class="form-label">Middle Initial</label>
                            <input type="text" name="middlename" placeholder="Middle Initial">
                        </div>
                    </div>

                    <div class="inputSection">
                        <div class="colInputSection">
                            <label for="lastname" class="form-label">Last Name*</label>
                            <input type="text" name="lastname" placeholder="Last Name" required>
                        </div>
                        <div class="colInputSection">
                            <label for="platenumber" class="form-label">Tricycle Number*</label>
                            <input type="text" name="platenumber" placeholder="Tricycle Number" required>
                        </div>
                    </div>

                    <div class="inputSection">
                        <div class="colInputSection">
                            <label for="contactnumber" class="form-label">Contact Number</label>
                            <input type="text" name="contact" id="contactnumber" placeholder="09XXXXXXXXX" maxlength="11" pattern="[0-9]{11}" title="Please enter exactly 11 digits">
                            <small style="color:#6b7280; font-size:12px; display:block; margin-top:4px;">Must be exactly 11 digits</small>
                        </div>
                    </div>

                    <input type="hidden" name="profile_image" id="profileImageData">
                    <button type="submit" class="submit-btn" id="submitBtn" disabled>Save User</button>
                </form>
            </div>
        </div>

        <!-- Camera Modal -->
        <div class="camera-modal" id="cameraModal">
            <div class="camera-content">
                <div class="camera-header">
                    <h2>Take Profile Picture</h2>
                    <button class="close-btn" id="closeCameraModal">&times;</button>
                </div>
                <div class="camera-area">
                    <video id="cameraVideo" autoplay playsinline></video>
                    <canvas id="capturedCanvas"></canvas>
                    <div class="camera-controls">
                        <button type="button" class="camera-btn" id="captureBtn"><i class="fa-solid fa-camera"></i> Capture</button>
                        <button type="button" class="camera-btn secondary" id="retakeBtn" style="display:none;"><i class="fa-solid fa-redo"></i> Retake</button>
                        <button type="button" class="camera-btn" id="confirmBtn" style="display:none;"><i class="fa-solid fa-check"></i> Confirm</button>
                        <button type="button" class="camera-btn danger" id="cancelCameraBtn"><i class="fa-solid fa-times"></i> Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Driver Modal -->
        <div class="modal" id="editModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Drivers</h2>
                    <button class="close-btn" id="closeEditModal">&times;</button>
                </div>
                <form id="editUserForm" method="post" action="../../api/manage-drivers/updateuser.php" enctype="multipart/form-data">
                    <input type="hidden" name="driver_id" id="edit_driver_id">
                    <div class="profile-picture-section">
                        <label class="form-label">Profile Picture</label>
                        <div class="profile-picture-container" id="editProfilePictureContainer">
                            <div class="profile-picture-placeholder">
                                <i class="fa-solid fa-camera"></i><span>Change Photo</span>
                            </div>
                        </div>
                        <div id="editStatusMessage" class="status-message" style="display:none;"></div>
                    </div>

                    <div class="inputSection">
                        <div class="colInputSection">
                            <label for="edit_firstname" class="form-label">First Name*</label>
                            <input type="text" name="firstname" id="edit_firstname" placeholder="First Name" required>
                        </div>
                        <div class="colInputSection">
                            <label for="edit_middlename" class="form-label">Middle Initial</label>
                            <input type="text" name="middlename" id="edit_middlename" placeholder="Middle Initial">
                        </div>
                    </div>

                    <div class="inputSection">
                        <div class="colInputSection">
                            <label for="edit_lastname" class="form-label">Last Name*</label>
                            <input type="text" name="lastname" id="edit_lastname" placeholder="Last Name" required>
                        </div>
                        <div class="colInputSection">
                            <label for="edit_platenumber" class="form-label">Tricycle Number*</label>
                            <input type="text" name="platenumber" id="edit_platenumber" placeholder="Tricycle Number" required>
                        </div>
                    </div>

                    <div class="inputSection">
                        <div class="colInputSection">
                            <label for="edit_contact" class="form-label">Contact Number</label>
                            <input type="text" name="contact" id="edit_contact" placeholder="09XXXXXXXXX"  maxlength="11" pattern="[0-9]{11}" title="Please enter exactly 11 digits">
                            <small style="color:#6b7280; font-size:12px; display:block; margin-top:4px;">Must be exactly 11 digits</small>
                        </div>
                    </div>

                    <input type="hidden" name="profile_image" id="editProfileImageData">
                    <input type="hidden" name="existing_image" id="existingImagePath">
                    <button type="submit" class="submit-btn" id="editSubmitBtn">Update User</button>
                </form>
            </div>
        </div>

        <!-- Edit Camera Modal -->
        <div class="camera-modal" id="editCameraModal">
            <div class="camera-content">
                <div class="camera-header">
                    <h2>Update Profile Picture</h2>
                    <button class="close-btn" id="closeEditCameraModal">&times;</button>
                </div>
                <div class="camera-area">
                    <video id="editCameraVideo" autoplay playsinline></video>
                    <canvas id="editCapturedCanvas"></canvas>
                    <div class="camera-controls">
                        <button type="button" class="camera-btn" id="editCaptureBtn"><i class="fa-solid fa-camera"></i> Capture</button>
                        <button type="button" class="camera-btn secondary" id="editRetakeBtn" style="display:none;"><i class="fa-solid fa-redo"></i> Retake</button>
                        <button type="button" class="camera-btn" id="editConfirmBtn" style="display:none;"><i class="fa-solid fa-check"></i> Confirm</button>
                        <button type="button" class="camera-btn danger" id="cancelEditCameraBtn"><i class="fa-solid fa-times"></i> Cancel</button>
                    </div>
                </div>
            </div>
        </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://kit.fontawesome.com/4c7e22a859.js" crossorigin="anonymous"></script>
    <script src="../../assets/js/manage-drivers/managedrivers.js"></script>
    </body>
    </html>
