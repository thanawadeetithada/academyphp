<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// ==========================================
// API: จัดการข้อมูลผู้ใช้งาน (Backend)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0); // ป้องกัน Error HTML แทรกใน JSON
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        switch ($action) {
            
            // 1. ดึงข้อมูลคอร์สเรียนทั้งหมด (สำหรับ Modal เลือกคอร์ส)
            case 'getAllCourses':
                // เพิ่ม WHERE deleted_at IS NULL เพื่อไม่ให้เลือกคอร์สที่โดนลบไปแล้ว
                $stmt = $conn->prepare("SELECT course_id as id, name, level FROM courses WHERE deleted_at IS NULL ORDER BY created_at DESC");
                $stmt->execute();
                $result = $stmt->get_result();
                $courses = [];
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
                echo json_encode($courses);
                break;

            // 2. ดึงข้อมูลผู้ใช้งานทั้งหมด พร้อมคอร์สที่ลงทะเบียน
            case 'getAllUsers':
                // ใช้ DISTINCT ภายใน GROUP_CONCAT เพื่อไม่ให้คอร์สแสดงซ้ำ
                // และตรวจสอบ e.deleted_at IS NULL และ u.deleted_at IS NULL (เพื่อไม่ดึง User ที่โดนลบ)
                $sql = "SELECT u.*, 
                               GROUP_CONCAT(DISTINCT c.course_id SEPARATOR ',') as course_ids, 
                               GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as course_names 
                        FROM users u 
                        LEFT JOIN enrollments e ON u.user_id = e.user_id AND e.deleted_at IS NULL
                        LEFT JOIN courses c ON e.course_id = c.course_id 
                        WHERE u.deleted_at IS NULL 
                        GROUP BY u.user_id 
                        ORDER BY u.created_at DESC";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $users = [];
                while ($row = $result->fetch_assoc()) {
                    // แปลง Role จาก Eng เป็น Thai สำหรับ UI
                    $displayRole = ($row['role'] === 'admin') ? 'แอดมิน' : 'นักเรียน';
                    
                    $users[] = [
                        'userId' => $row['user_id'],
                        'fullName' => $row['full_name'],
                        'nickname' => $row['nickname'],
                        'grade' => $row['grade'],
                        'school' => $row['school'],
                        'phone' => $row['phone'],
                        'parentName' => $row['parent_name'],
                        'parentPhone' => $row['parent_phone'],
                        'address' => $row['address'],
                        'role' => $displayRole,
                        'courseIds' => $row['course_ids'] ?? '', // รหัสคอร์สที่ไม่ซ้ำ
                        'courseNames' => $row['course_names'] ?? '' // ชื่อคอร์สที่ไม่ซ้ำ
                    ];
                }
                echo json_encode($users);
                break;

            // 3. บันทึก / แก้ไข ข้อมูลผู้ใช้งาน
            case 'saveUser':
                $userId = $data['userId'] ?? '';
                $roleDB = ($data['role'] === 'แอดมิน') ? 'admin' : 'student';
                
                $passHash = '';
                if (!empty($data['password'])) {
                    $passHash = password_hash($data['password'], PASSWORD_DEFAULT);
                }

                if (!empty($userId)) {
                    // =================== กรณี: แก้ไขข้อมูลเดิม (UPDATE) ===================
                    
                    // อัปเดตข้อมูลทั่วไป
                    if ($passHash !== '') {
                        $sql = "UPDATE users SET full_name=?, nickname=?, grade=?, school=?, phone=?, parent_name=?, parent_phone=?, address=?, role=?, password_hash=? WHERE user_id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$data['fullName'], $data['nickname'], $data['grade'], $data['school'], $data['phone'], $data['parentName'], $data['parentPhone'], $data['address'], $roleDB, $passHash, $userId]);
                    } else {
                        $sql = "UPDATE users SET full_name=?, nickname=?, grade=?, school=?, phone=?, parent_name=?, parent_phone=?, address=?, role=? WHERE user_id=?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$data['fullName'], $data['nickname'], $data['grade'], $data['school'], $data['phone'], $data['parentName'], $data['parentPhone'], $data['address'], $roleDB, $userId]);
                    }
                    $targetUserId = $userId;

                } else {
                    // =================== กรณี: เพิ่มผู้ใช้งานใหม่ (INSERT) ===================
                    
                    // สร้างรหัสประจำตัวใหม่ ST-XXXX หรือ AD-XXXX
                    $prefix = ($roleDB === 'admin') ? 'AD-' : 'ST-';
                    $sql_id = "SELECT user_id FROM users WHERE user_id LIKE '$prefix%' ORDER BY CAST(SUBSTRING(user_id, 4) AS UNSIGNED) DESC LIMIT 1";
                    $res_id = $conn->query($sql_id);
                    
                    $nextIdNum = 1;
                    if ($res_id->num_rows > 0) {
                        $lastId = $res_id->fetch_assoc()['user_id'];
                        $nextIdNum = intval(substr($lastId, 3)) + 1;
                    }
                    $targetUserId = $prefix . str_pad($nextIdNum, 4, '0', STR_PAD_LEFT);

                    $sql = "INSERT INTO users (user_id, full_name, nickname, grade, school, phone, parent_name, parent_phone, address, role, password_hash) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$targetUserId, $data['fullName'], $data['nickname'], $data['grade'], $data['school'], $data['phone'], $data['parentName'], $data['parentPhone'], $data['address'], $roleDB, $passHash]);
                }

                // =================== ซิงค์คอร์สเรียน (Enrollments) ===================
                if ($roleDB === 'student') {
                    $newCourses = array_filter(array_map('trim', explode(',', $data['courses'])));
                    
                    // 1. ดึงคอร์สเดิมที่มีอยู่ (เช็คเฉพาะที่ยังไม่โดน Soft Delete)
                    $stmt = $conn->prepare("SELECT course_id FROM enrollments WHERE user_id = ? AND deleted_at IS NULL");
                    $stmt->execute([$targetUserId]);
                    $res = $stmt->get_result();
                    $existingCourses = [];
                    while ($row = $res->fetch_assoc()) {
                        $existingCourses[] = $row['course_id'];
                    }

                    // 2. ลบคอร์สที่โดนติ๊กออก 
                    $coursesToDelete = array_diff($existingCourses, $newCourses);
                    if (!empty($coursesToDelete)) {
                        $placeholders = implode(',', array_fill(0, count($coursesToDelete), '?'));
                        $delStmt = $conn->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id IN ($placeholders)");
                        $params = array_merge([$targetUserId], $coursesToDelete);
                        $delStmt->execute($params);
                    }

                    // 3. เพิ่มคอร์สที่ติ๊กใหม่
                    $coursesToAdd = array_diff($newCourses, $existingCourses);
                    if (!empty($coursesToAdd)) {
                        $insStmt = $conn->prepare("INSERT INTO enrollments (enroll_id, user_id, course_id, approval_status, payment_status) VALUES (?, ?, ?, 'approved', 'pending_payment')");
                        foreach ($coursesToAdd as $cId) {
                            $enrollId = 'EN' . time() . rand(10, 99);
                            $insStmt->execute([$enrollId, $targetUserId, $cId]);
                        }
                    }
                } else {
                    // ถ้าเป็นแอดมิน ให้ลบข้อมูลคอร์สออกให้หมด
                    $stmt = $conn->prepare("DELETE FROM enrollments WHERE user_id = ?");
                    $stmt->execute([$targetUserId]);
                }

                echo json_encode(['success' => true]);
                break;

            // 4. ลบข้อมูลผู้ใช้งาน (เปลี่ยนเป็น Soft Delete)
            case 'deleteUser':
                $userId = $data['userId'];
                // ใช้การ UPDATE แทน DELETE
                $stmt = $conn->prepare("UPDATE users SET deleted_at = CURRENT_TIMESTAMP WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                echo json_encode(['success' => true]);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    
    exit; // จบการทำงานของ API
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - จัดการผู้ใช้งาน</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .app-layout { display: flex; min-height: 100vh; overflow-x: hidden; }
    .sidebar { width: 260px; transition: all 0.3s; flex-shrink: 0; z-index: 1000; }
    .nav-item { display: block; padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
    .nav-item:hover { background-color: rgba(255,255,255,0.1); }
    .main-content { flex-grow: 1; padding: 20px; transition: all 0.3s; width: 100%; }
    .mobile-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 999; }
    
    @media (max-width: 991.98px) {
      .sidebar { position: fixed; left: -260px; height: 100vh; }
      .sidebar.show { left: 0; }
      .mobile-overlay.show { display: block; }
    }
    @media (min-width: 992px) {
      .btn-toggle-menu { display: none !important; }
    }
    
    .full-page-overlay {
      position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(255,255,255,0.8); z-index: 1060;
      display: flex; flex-direction: column; justify-content: center; align-items: center;
      visibility: hidden; opacity: 0; transition: opacity 0.3s;
    }
    .full-page-overlay.show { visibility: visible; opacity: 1; }
  </style>
</head>
<body>

<div class="app-layout">

  <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

  <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;" id="adminSidebar">
    <div class="sidebar-header border-bottom border-secondary pt-4 pb-3 position-relative">
      <button class="btn text-white position-absolute top-0 end-0 m-2 d-lg-none" style="background: transparent; border: none; font-size: 1.5rem;" onclick="toggleSidebar()">
        <i class="bi bi-x-lg"></i>
      </button>
      <div class="d-flex align-items-center justify-content-center mb-2">
        <img src="img/logo.png" onerror="this.src='https://via.placeholder.com/50'" style="width: 50px; height: 50px; object-fit: contain;"> 
      </div>
      <h5 class="fw-bold mb-0 text-center">Sci Math Academy</h5>
      <div class="text-center"><small style="color: #cbd5e1;">สถาบันสอนพิเศษ สว่างแดนดิน</small></div>
    </div>

    <div class="nav-menu mt-3 flex-grow-1 px-3">
      <a href="dashboard.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-grid-1x2 me-2"></i> แดชบอร์ด</a>
      <a href="admin_courses.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-journal-bookmark me-2"></i> จัดการคอร์ส</a>
      <a href="admin_users.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;">
        <i class="bi bi-people-fill text-primary me-2"></i> จัดการผู้ใช้
      </a>
      <a href="admin_slips.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-receipt me-2"></i> จัดการสลิป</a>
      <a href="admin_news.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-megaphone-fill me-2"></i> ข่าวสาร</a>
    </div>
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5" style="background-color: #f4f6f9;">
    
    <div class="d-lg-none mb-3">
      <button class="btn-toggle-menu btn btn-light shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div id="userTableView">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <h4 class="fw-bold mb-0 text-dark text-nowrap">จัดการข้อมูลผู้ใช้งาน</h4>
        
        <div class="d-flex flex-column flex-sm-row gap-2 w-100 justify-content-md-end" style="max-width: 650px;">
          <input type="text" class="form-control border-0 shadow-sm flex-grow-1" placeholder="ค้นหานักเรียน..." onkeyup="searchUsers(this.value)">
          <button class="btn text-white px-4 shadow-sm text-nowrap" style="background-color: #2b4d7e; border-radius: 8px;" onclick="openAddUser()">
            <i class="bi bi-plus-lg"></i> เพิ่ม
          </button>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr class="text-nowrap">
                <th class="py-3 px-4 fw-bold border-0">ชื่อ-สกุล</th>
                <th class="py-3 fw-bold border-0">ชั้น</th>
                <th class="py-3 fw-bold border-0">โรงเรียน</th>
                <th class="py-3 fw-bold border-0">คอร์ส</th>
                <th class="py-3 fw-bold border-0 text-center">สิทธิ์ผู้ใช้งาน</th>
                <th class="py-3 px-4 fw-bold border-0 text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody id="userTableBody" style="border-top: none;">
              <tr><td colspan="6" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูล...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="userEditView" style="display: none;">
      <div class="d-flex align-items-center mb-4">
        <button class="btn me-1" onclick="closeEditForm()"><i class="bi bi-arrow-left fs-3"></i></button>
        <h4 class="fw-bold mb-0 text-dark" id="editFormTitle">แก้ไขข้อมูลนักเรียน</h4>
      </div>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5">
          <form id="editUserForm" class="row g-4">
            <input type="hidden" id="eUserId">
            
            <h6 class="fw-bold text-primary mb-0 border-bottom pb-2">ข้อมูลส่วนตัว</h6>
            <div class="col-md-6">
              <label class="form-label small fw-bold">ชื่อ-สกุล <span class="text-danger">*</span></label>
              <input type="text" class="form-control bg-light border-0" id="eFullName" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">ชื่อเล่น <span class="text-danger">*</span></label>
              <input type="text" class="form-control bg-light border-0" id="eNickname" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">ระดับชั้น <span class="text-danger">*</span></label>
              <input type="text" class="form-control bg-light border-0" id="eGrade" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">โรงเรียน <span class="text-danger">*</span></label>
              <input type="text" class="form-control bg-light border-0" id="eSchool" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">เบอร์โทรศัพท์นักเรียน <span class="text-danger">*</span></label>
              <input type="text" class="form-control bg-light border-0" id="ePhone" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-bold">ที่อยู่ <span class="text-danger">*</span></label>
              <textarea class="form-control bg-light border-0" id="eAddress" rows="2" required></textarea>
            </div>

            <h6 class="fw-bold text-primary mb-0 border-bottom pb-2 mt-4">ข้อมูลผู้ปกครองและการเรียน</h6>
            <div class="col-md-6">
              <label class="form-label small fw-bold">ชื่อผู้ปกครอง <span class="text-danger">*</span></label>
              <input type="text" class="form-control bg-light border-0" id="eParentName" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">เบอร์โทรศัพท์ผู้ปกครอง <span class="text-danger">*</span></label>
              <input type="text" class="form-control bg-light border-0" id="eParentPhone" required>
            </div>
            
            <div class="col-md-6">
              <label class="form-label small fw-bold">สิทธิ์ผู้ใช้งาน <span class="text-danger">*</span></label>
              <select class="form-select bg-light border-0" id="eRole" onchange="handleRoleChange()" required>
                <option value="นักเรียน">นักเรียน</option>
                <option value="แอดมิน">แอดมิน</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label small fw-bold">คอร์สที่ลงทะเบียน <span class="text-danger" id="courseRequiredStar">*</span></label>
              <input type="hidden" id="eCoursesIds">
              <div class="input-group">
                <input type="text" class="form-control bg-light border-0" id="eCourses" readonly placeholder="คลิกปุ่มเพื่อเลือกคอร์สเรียน...">
                <button class="btn btn-outline-primary" type="button" id="btnSelectCourse" onclick="openCourseSelectModal()"><i class="bi bi-list-check"></i> เลือกคอร์ส</button>
              </div>
            </div>

            <h6 class="fw-bold text-primary mb-0 border-bottom pb-2 mt-4">ข้อมูลความปลอดภัย (ตั้งรหัสผ่าน)</h6>
            <div class="col-md-6">
              <label class="form-label small fw-bold">รหัสผ่าน <span class="text-muted fw-normal" id="passHint">(เว้นว่างถ้าไม่ต้องการเปลี่ยน)</span></label>
              <div class="input-group">
                <input type="password" class="form-control bg-light border-0" id="ePassword" placeholder="กรอกรหัสผ่าน">
                <button class="btn bg-light text-secondary border-0" type="button" onclick="togglePass('ePassword', 'eyeIcon1')"><i class="bi bi-eye" id="eyeIcon1"></i></button>
              </div>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-bold">ยืนยันรหัสผ่าน</label>
              <div class="input-group">
                <input type="password" class="form-control bg-light border-0" id="eConfirmPassword" placeholder="กรอกรหัสผ่านอีกครั้ง">
                <button class="btn bg-light text-secondary border-0" type="button" onclick="togglePass('eConfirmPassword', 'eyeIcon2')"><i class="bi bi-eye" id="eyeIcon2"></i></button>
              </div>
            </div>
            <div class="col-12 mt-1">
              <div id="passwordErrorMsg" class="text-danger small d-none"><i class="bi bi-exclamation-triangle-fill"></i> รหัสผ่านไม่ตรงกัน หรือยังไม่ได้กรอกรหัสผ่าน</div>
            </div>

            <div class="col-12 mt-4 d-flex flex-column-reverse flex-sm-row justify-content-end gap-2">
              <button type="button" class="btn btn-light px-4 w-sm-auto border-secondary" onclick="closeEditForm()">ยกเลิก</button>
              <button type="button" class="btn text-white px-5 w-sm-auto" style="background-color: #2b4d7e;" id="btnSaveUser" onclick="saveUserData()">บันทึกข้อมูล</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-body text-center p-5">
          <i class="bi bi-exclamation-circle text-danger mb-4" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mb-2">ยืนยันการลบข้อมูล</h5>
          <p class="text-muted">คุณต้องการลบผู้ใช้งาน <span id="deleteUserName" class="fw-bold text-dark"></span> ออกจากระบบใช่หรือไม่?</p>
          <input type="hidden" id="deleteUserIdTarget">
          <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
            <button type="button" class="btn btn-danger px-4" onclick="confirmDeleteUser()" style="border-radius: 8px;">ใช่, ยืนยันการลบ</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="courseSelectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-header bg-light border-bottom pt-4 pb-3 px-4">
          <div>
            <h5 class="modal-title fw-bold text-dark mb-1">เลือกคอร์สที่ลงทะเบียน</h5>
            <small class="text-muted">คุณสามารถค้นหาและเลือกได้หลายคอร์สพร้อมกัน</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4 bg-white">
          <div class="input-group mb-3 shadow-sm border-0 rounded-3 overflow-hidden">
            <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control bg-light border-0" id="searchCourseInput" placeholder="ค้นหาชื่อคอร์ส หรือ รหัสคอร์ส..." onkeyup="filterAvailableCourses(this.value)">
          </div>
          <div class="list-group" id="availableCoursesList">
            <div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดคอร์ส...</div>
          </div>
        </div>
        <div class="modal-footer bg-light border-top border-light d-flex justify-content-between align-items-center">
          <span class="text-muted small fw-bold" id="selectedCourseCount">เลือกแล้ว 0 คอร์ส</span>
          <div>
            <button type="button" class="btn btn-light border-secondary me-2" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="button" class="btn text-white fw-medium px-4" style="background-color: #0d4ba5; border-radius: 8px;" onclick="confirmCourseSelection()">ยืนยันการเลือก</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="adminUserLoading" class="full-page-overlay">
    <div class="spinner-border text-secondary" style="width: 4rem; height: 4rem;" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3 fw-bold text-secondary" id="adminLoadingText">กำลังประมวลผล...</h4>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'admin_users.php'; // เรียกใช้ไฟล์ตัวเอง
  
  let allUsersList = [];
  let allCoursesForSelect = [];
  let tempSelectedCourseIds = [];
  
  let deleteModalInstance;
  let courseSelectModalInstance;

  function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  // ฟังก์ชันสื่อสารกับ PHP API
  async function callAPI(action, data = null) {
    try {
      const options = {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: data ? JSON.stringify(data) : null
      };
      const response = await fetch(`${API_URL}?action=${action}`, options);
      return await response.json();
    } catch (error) {
      console.error("API Error:", error);
      return { success: false, message: error.message };
    }
  }

  // ================= Setup เริ่มต้น =================
  document.addEventListener("DOMContentLoaded", function() {
    loadInitialData();
  });

  function loadInitialData() {
    showAdminLoading('กำลังโหลดข้อมูล...');
    
    // ดึงคอร์สเรียนเตรียมไว้สำหรับ Modal
    callAPI('getAllCourses').then(courses => {
      allCoursesForSelect = courses || [];
      
      // ดึงรายชื่อผู้ใช้
      callAPI('getAllUsers').then(users => {
        allUsersList = users || [];
        displayUsersTable(allUsersList);
        hideAdminLoading();
      });
    });
  }

  // ================= UI จัดการตาราง =================
  function displayUsersTable(usersData) {
    const tbody = document.getElementById('userTableBody');
    tbody.innerHTML = '';
    
    if(!usersData || usersData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">ไม่พบข้อมูลผู้ใช้งาน</td></tr>';
      return;
    }

    usersData.forEach(user => {
      let roleBadge = user.role === 'แอดมิน' 
        ? '<span class="badge rounded-pill bg-primary-subtle text-primary px-3 py-2 fw-medium"><i class="bi bi-shield-lock-fill me-1"></i>แอดมิน</span>' 
        : '<span class="badge rounded-pill bg-info-subtle text-info px-3 py-2 fw-medium"><i class="bi bi-person-fill me-1"></i>นักเรียน</span>';

      let courseNamesDisplay = user.courseNames ? user.courseNames : '-';

      tbody.innerHTML += `
        <tr>
          <td class="px-4 py-3 text-dark fw-medium text-nowrap">${user.fullName}</td>
          <td class="py-3 text-dark text-nowrap">${user.grade}</td>
          <td class="py-3 text-dark text-nowrap">${user.school}</td>
          <td class="py-3 text-dark" style="min-width: 150px;">${courseNamesDisplay}</td>
          <td class="py-3 text-center text-nowrap">${roleBadge}</td>
          <td class="px-4 py-3 text-center text-nowrap">
            <button class="btn btn-sm btn-outline-primary me-2" onclick="openEditForm('${user.userId}')" title="แก้ไขข้อมูล">
              <i class="bi bi-pencil-square"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger" onclick="promptDeleteUser('${user.userId}', '${user.fullName}')" title="ลบข้อมูล">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
      `;
    });
  }

  function searchUsers(keyword) {
    keyword = keyword.toLowerCase();
    const filtered = allUsersList.filter(u => 
      (u.fullName && u.fullName.toLowerCase().includes(keyword)) || 
      (u.school && u.school.toLowerCase().includes(keyword)) ||
      (u.phone && u.phone.includes(keyword))
    );
    displayUsersTable(filtered);
  }

  // ================= UI ฟอร์มเพิ่ม/แก้ไข =================
  function handleRoleChange() {
    let role = document.getElementById('eRole').value;
    let courseBtn = document.getElementById('btnSelectCourse');
    let eCoursesInput = document.getElementById('eCourses');
    let star = document.getElementById('courseRequiredStar');
    
    if (role === 'แอดมิน') {
      courseBtn.disabled = true;
      document.getElementById('eCoursesIds').value = '';
      eCoursesInput.value = 'สิทธิ์แอดมินไม่ต้องเลือกลงคอร์ส';
      if (star) star.classList.add('d-none');
    } else {
      courseBtn.disabled = false;
      if(eCoursesInput.value === 'สิทธิ์แอดมินไม่ต้องเลือกลงคอร์ส') {
        eCoursesInput.value = '';
      }
      if (star) star.classList.remove('d-none');
    }
  }

  function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    if(input.type === 'password') {
      input.type = 'text';
      icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
      input.type = 'password';
      icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
  }

  function openAddUser() {
    document.getElementById('editUserForm').reset();
    document.getElementById('eUserId').value = '';
    document.getElementById('editFormTitle').innerText = 'เพิ่มผู้ใช้งานใหม่';
    document.getElementById('passHint').innerText = '(จำเป็นต้องตั้งรหัสผ่าน)';
    document.getElementById('passwordErrorMsg').classList.add('d-none');
    
    document.getElementById('eCourses').value = ''; 
    document.getElementById('eCoursesIds').value = ''; 
    document.getElementById('eRole').value = 'นักเรียน';

    handleRoleChange();

    document.getElementById('userTableView').style.display = 'none';
    document.getElementById('userEditView').style.display = 'block';
  }

  function openEditForm(userId) {
    const user = allUsersList.find(u => u.userId === userId);
    if(!user) return;
    
    document.getElementById('editFormTitle').innerText = 'แก้ไขข้อมูลนักเรียน';
    document.getElementById('passHint').innerText = '(เว้นว่างถ้าไม่ต้องการเปลี่ยน)';
    document.getElementById('passwordErrorMsg').classList.add('d-none');
    
    document.getElementById('eUserId').value = user.userId;
    document.getElementById('eFullName').value = user.fullName;
    document.getElementById('eNickname').value = user.nickname;
    document.getElementById('eGrade').value = user.grade;
    document.getElementById('eSchool').value = user.school;
    document.getElementById('ePhone').value = user.phone;
    document.getElementById('eAddress').value = user.address;
    document.getElementById('eParentName').value = user.parentName;
    document.getElementById('eParentPhone').value = user.parentPhone;
    
    document.getElementById('eCoursesIds').value = user.courseIds; 
    document.getElementById('eCourses').value = user.courseNames; 
    
    document.getElementById('eRole').value = user.role || 'นักเรียน';
    document.getElementById('ePassword').value = '';
    document.getElementById('eConfirmPassword').value = '';

    handleRoleChange();

    document.getElementById('userTableView').style.display = 'none';
    document.getElementById('userEditView').style.display = 'block';
    window.scrollTo(0, 0);
  }

  function closeEditForm() {
    document.getElementById('userEditView').style.display = 'none';
    document.getElementById('userTableView').style.display = 'block';
  }

  function saveUserData() {
    const form = document.getElementById('editUserForm');
    if (!form.reportValidity()) return; 

    const userId = document.getElementById('eUserId').value;
    const pass = document.getElementById('ePassword').value;
    const cpass = document.getElementById('eConfirmPassword').value;
    const errMsg = document.getElementById('passwordErrorMsg');

    errMsg.classList.add('d-none');

    if (pass !== cpass) {
      errMsg.innerText = "รหัสผ่านและยืนยันรหัสผ่านไม่ตรงกัน";
      errMsg.classList.remove('d-none');
      return;
    }

    if (!userId && pass.trim() === '') {
      errMsg.innerText = "กรุณากำหนดรหัสผ่านสำหรับผู้ใช้งานใหม่";
      errMsg.classList.remove('d-none');
      return;
    }

    showAdminLoading('กำลังบันทึกข้อมูล...');

    const payload = {
      userId: userId,
      fullName: document.getElementById('eFullName').value,
      nickname: document.getElementById('eNickname').value,
      grade: document.getElementById('eGrade').value,
      school: document.getElementById('eSchool').value,
      phone: document.getElementById('ePhone').value,
      address: document.getElementById('eAddress').value,
      parentName: document.getElementById('eParentName').value,
      parentPhone: document.getElementById('eParentPhone').value,
      courses: document.getElementById('eCoursesIds').value,
      role: document.getElementById('eRole').value,
      password: pass
    };

    callAPI('saveUser', payload).then(res => {
      if(res.success) {
        closeEditForm();
        loadInitialData(); // รีโหลดข้อมูลใหม่
      } else {
        hideAdminLoading();
        alert('เกิดข้อผิดพลาด: ' + res.message);
      }
    });
  }

  // ================= UI การลบข้อมูล =================
  function promptDeleteUser(userId, name) {
    document.getElementById('deleteUserIdTarget').value = userId;
    document.getElementById('deleteUserName').innerText = name;
    deleteModalInstance = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
    deleteModalInstance.show();
  }

  function confirmDeleteUser() {
    const userId = document.getElementById('deleteUserIdTarget').value;
    deleteModalInstance.hide();
    showAdminLoading('กำลังลบข้อมูล...');
    
    callAPI('deleteUser', { userId: userId }).then(res => {
      if(res.success) {
        loadInitialData();
      } else {
        hideAdminLoading();
        alert('ลบไม่สำเร็จ: ' + res.message);
      }
    });
  }

  // ================= UI เลือกคอร์สเรียน (Modal) =================
  function openCourseSelectModal() {
    if(!courseSelectModalInstance) {
      courseSelectModalInstance = new bootstrap.Modal(document.getElementById('courseSelectModal'));
    }
    
    let currentCoursesStr = document.getElementById('eCoursesIds').value; 
    tempSelectedCourseIds = currentCoursesStr ? currentCoursesStr.split(',').map(c => c.trim()).filter(c => c !== '') : [];
    
    document.getElementById('searchCourseInput').value = '';
    displayAvailableCourses(allCoursesForSelect);
    updateSelectedCourseCountUI();
    courseSelectModalInstance.show();
  }

  function displayAvailableCourses(courses) {
    const listDiv = document.getElementById('availableCoursesList');
    listDiv.innerHTML = '';
    if(courses.length === 0) {
      listDiv.innerHTML = '<div class="text-center py-4 text-muted">ไม่พบข้อมูลคอร์สเรียน</div>';
      return;
    }
    courses.forEach(course => {
      let isChecked = tempSelectedCourseIds.includes(course.id) ? 'checked' : '';
      listDiv.innerHTML += `
        <label class="list-group-item list-group-item-action d-flex align-items-center py-3 border-0 border-bottom" style="cursor: pointer;">
          <input class="form-check-input mt-0 me-3 course-checkbox" type="checkbox" value="${course.id}" ${isChecked} onchange="toggleCourseSelection('${course.id}', this.checked)">
          <div>
            <h6 class="mb-1 fw-bold text-dark">${course.name}</h6>
            <small class="text-muted"><i class="bi bi-tag me-1"></i> ระดับ: ${course.level}</small>
          </div>
        </label>
      `;
    });
  }

  function filterAvailableCourses(keyword) {
    keyword = String(keyword).toLowerCase().trim();
    const filtered = allCoursesForSelect.filter(c => 
      String(c.name || '').toLowerCase().includes(keyword) ||
      String(c.id || '').toLowerCase().includes(keyword) ||
      String(c.level || '').toLowerCase().includes(keyword)
    );
    displayAvailableCourses(filtered);
  }

  function toggleCourseSelection(id, isChecked) {
    if (isChecked) {
      if (!tempSelectedCourseIds.includes(id)) tempSelectedCourseIds.push(id);
    } else {
      tempSelectedCourseIds = tempSelectedCourseIds.filter(val => val !== id);
    }
    updateSelectedCourseCountUI();
  }

  function updateSelectedCourseCountUI() {
    document.getElementById('selectedCourseCount').innerText = `เลือกแล้ว ${tempSelectedCourseIds.length} คอร์ส`;
  }

  function confirmCourseSelection() {
    let selectedIdsStr = tempSelectedCourseIds.join(',');
    
    // หาชื่อคอร์สเพื่อมาโชว์ใน Input
    let names = tempSelectedCourseIds.map(id => {
      let found = allCoursesForSelect.find(c => c.id === id);
      return found ? found.name : id; 
    });

    document.getElementById('eCoursesIds').value = selectedIdsStr;
    document.getElementById('eCourses').value = names.join(', ');
    courseSelectModalInstance.hide();
  }

  function showAdminLoading(text) {
    const loadingOverlay = document.getElementById('adminUserLoading');
    if (loadingOverlay) {
      document.getElementById('adminLoadingText').innerText = text;
      loadingOverlay.classList.add('show');
    }
  }

  function hideAdminLoading() {
    const loadingOverlay = document.getElementById('adminUserLoading');
    if (loadingOverlay) loadingOverlay.classList.remove('show');
  }
</script>
</body>
</html>