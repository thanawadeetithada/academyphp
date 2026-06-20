<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ (ยอมรับทั้ง 'student' และ 'นักเรียน')
$role = $_SESSION['sessionRole'] ?? '';
if ($role !== 'student' && $role !== 'นักเรียน') {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['userRowId'] ?? ''; ''; // รหัสประจำตัวนักเรียน (เช่น ST-XXXX)

// ==========================================
// API: ดึงและอัปเดตข้อมูลส่วนตัวของนักเรียน
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0); // ป้องกัน Error แทรกใน JSON
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        if ($action === 'getProfile') {
            // ดึงข้อมูลส่วนตัว (เทียบเท่า getStudentProfileBE)
            $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->get_result()->fetch_assoc();
            
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'id' => $user['user_id'],
                    'fullName' => $user['full_name'],
                    'nickname' => $user['nickname'],
                    'level' => $user['grade'],
                    'school' => $user['school'],
                    'phone' => $user['phone'],
                    'parentName' => $user['parent_name'],
                    'parentPhone' => $user['parent_phone'],
                    'address' => $user['address']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลผู้ใช้งาน']);
            }

        } elseif ($action === 'updateProfile') {
            // อัปเดตข้อมูลส่วนตัว (เทียบเท่า updateStudentProfileBE)
            $fullName = $data['fullName'];
            $nickname = $data['nickname'];
            $level = $data['level'];
            $school = $data['school'];
            $phone = $data['phone'];
            $parentName = $data['parentName'];
            $parentPhone = $data['parentPhone'];
            $address = $data['address'];
            $password = $data['password'] ?? '';

            // ตรวจสอบและเข้ารหัสรหัสผ่าน (ใช้ password_hash ของ PHP ซึ่งปลอดภัยกว่า MD5/SHA256)
            if (!empty($password) && trim($password) !== '') {
                $passHash = password_hash(trim($password), PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, nickname=?, grade=?, school=?, phone=?, parent_name=?, parent_phone=?, address=?, password_hash=? WHERE user_id=?");
                $stmt->execute([$fullName, $nickname, $level, $school, $phone, $parentName, $parentPhone, $address, $passHash, $userId]);
            } else {
                // ถ้าไม่เปลี่ยนรหัสผ่าน ก็ไม่อัปเดตฟิลด์รหัสผ่าน
                $stmt = $conn->prepare("UPDATE users SET full_name=?, nickname=?, grade=?, school=?, phone=?, parent_name=?, parent_phone=?, address=? WHERE user_id=?");
                $stmt->execute([$fullName, $nickname, $level, $school, $phone, $parentName, $parentPhone, $address, $userId]);
            }
            
            // อัปเดตชื่อใน Session ให้เป็นชื่อใหม่ (เผื่อแสดงที่มุมขวาบน)
            $_SESSION['sessionUser'] = $fullName;
            
            echo json_encode(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
    
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - ข้อมูลส่วนตัว</title>
  
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
    
    .full-page-overlay {
      display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(255,255,255,0.8); z-index: 1060;
      flex-direction: column; justify-content: center; align-items: center;
      transition: opacity 0.3s;
    }
    .full-page-overlay.show { display: flex; }
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" onclick="toggleSidebar()"></div>
  
  <aside class="sidebar text-white shadow-sm" style="background-color: #2b4d7e;" id="studentSidebar">
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
      <a href="main.php" class="nav-item text-white text-opacity-75 text-decoration-none">
        <i class="bi bi-house-door me-2"></i> หน้าหลัก
      </a>
      <a href="student_courses.php" class="nav-item text-white text-opacity-75 text-decoration-none">
        <i class="bi bi-collection me-2"></i> คอร์สทั้งหมด
      </a>
      <a href="student_payment.php" class="nav-item text-white text-opacity-75 text-decoration-none">
        <i class="bi bi-wallet2 me-2"></i> แจ้งชำระเงิน
      </a>
    </div>
    
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5" style="background-color: #f4f6f9;">
    <div class="d-lg-none mb-3">
      <button class="btn btn-light shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div id="studentProfileContent">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100" style="background-color: #2b4d7e; height: 6px;"></div>
            <div class="card-body p-4 p-md-5">
              
              <div class="d-flex align-items-center mb-4">
                <button class="btn btn-sm rounded-pill px-3" onclick="window.location.href='main.php'">
                  <i class="bi bi-arrow-left fs-5"></i>
                </button>
                <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i>ข้อมูลส่วนตัว</h4>
              </div>

              <div id="profileLoading" class="text-center p-5 text-muted">
                <span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลของคุณ...
              </div>

              <form id="studentProfileForm" style="display: none;">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">รหัสนักเรียน</label>
                    <input type="text" class="form-control bg-light border-0" id="prof-id" readonly>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ระดับชั้น</label>
                    <input type="text" class="form-control" id="prof-level" placeholder="เช่น ม.4, ม.5">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ชื่อ - นามสกุล</label>
                    <input type="text" class="form-control" id="prof-fullname">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ชื่อเล่น</label>
                    <input type="text" class="form-control" id="prof-nickname">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">โรงเรียน</label>
                    <input type="text" class="form-control" id="prof-school">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">เบอร์โทรศัพท์ (นักเรียน)</label>
                    <input type="text" class="form-control" id="prof-phone">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ชื่อผู้ปกครอง</label>
                    <input type="text" class="form-control" id="prof-parentName">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">เบอร์โทรศัพท์ (ผู้ปกครอง)</label>
                    <input type="text" class="form-control" id="prof-parentPhone">
                  </div>
                  <div class="col-12">
                    <label class="form-label text-muted small fw-bold">ที่อยู่</label>
                    <textarea class="form-control" id="prof-address" rows="3"></textarea>
                  </div>

                  <div class="col-12 mt-4">
                    <hr class="text-muted">
                    <h6 class="fw-bold text-dark"><i class="bi bi-key text-secondary me-2"></i>เปลี่ยนรหัสผ่าน <span class="text-muted small fw-normal">(เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</span></h6>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">รหัสผ่านใหม่</label>
                    <div class="position-relative">
                      <input type="password" class="form-control pe-5" id="prof-password">
                      <i class="bi bi-eye text-muted position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem;" onclick="togglePasswordVisibility('prof-password', this)"></i>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ยืนยันรหัสผ่านใหม่</label>
                    <div class="position-relative">
                      <input type="password" class="form-control pe-5" id="prof-confirmPassword">
                      <i class="bi bi-eye text-muted position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem;" onclick="togglePasswordVisibility('prof-confirmPassword', this)"></i>
                    </div>
                    <div id="prof-passwordMismatch" class="text-danger small mt-1 fw-bold" style="display: none;"><i class="bi bi-x-circle me-1"></i>รหัสผ่านไม่ตรงกัน</div>
                  </div>
                  
                  <div class="col-12 text-end mt-4">
                    <button type="button" class="btn btn-primary px-4 rounded-3 fw-bold" id="saveProfileBtn" onclick="saveStudentProfile()">
                      <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                    </button>
                  </div>
                </div>
              </form>

            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <div id="fullPageLoading" class="full-page-overlay">
    <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3 fw-bold text-primary" id="loadingText">กำลังประมวลผล...</h4>
  </div>

  <div class="modal fade" id="successProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-body text-center p-5">
          <i class="bi bi-check-circle-fill text-success mb-4" style="font-size: 4.5rem;"></i>
          <h4 class="fw-bold mb-2 text-dark">บันทึกข้อมูลสำเร็จ</h4>
          <p class="text-muted mb-4">ข้อมูลส่วนตัวของคุณได้รับการอัปเดตเรียบร้อยแล้ว</p>
          <button type="button" class="btn btn-primary px-5 rounded-pill fw-bold" data-bs-dismiss="modal" onclick="window.location.href='main.php'">ตกลง</button>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'student_profile.php';

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

  function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  function togglePasswordVisibility(inputId, icon) {
    const inputField = document.getElementById(inputId);
    if (inputField.type === "password") {
      inputField.type = "text";
      icon.classList.remove("bi-eye");
      icon.classList.add("bi-eye-slash");
    } else {
      inputField.type = "password";
      icon.classList.remove("bi-eye-slash");
      icon.classList.add("bi-eye");
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    // โหลดข้อมูลโปรไฟล์เมื่อเปิดหน้า
    loadStudentProfile();

    // ดักจับการพิมพ์รหัสผ่านใหม่ ให้เช็คว่าตรงกันหรือไม่
    const profileForm = document.getElementById('studentProfileForm');
    if(profileForm) {
      profileForm.addEventListener('input', function() {
        const pass = document.getElementById('prof-password').value;
        const confirmPass = document.getElementById('prof-confirmPassword').value;
        const mismatchWarning = document.getElementById('prof-passwordMismatch');
        
        if(confirmPass && pass !== confirmPass) {
          mismatchWarning.style.display = 'block';
          document.getElementById('prof-confirmPassword').classList.add('is-invalid');
        } else {
          mismatchWarning.style.display = 'none';
          document.getElementById('prof-confirmPassword').classList.remove('is-invalid');
        }
      });
    }
  });

  function loadStudentProfile() {
    callAPI('getProfile').then(data => {
      document.getElementById('profileLoading').style.display = 'none';
      if(data && data.success) {
        document.getElementById('studentProfileForm').style.display = 'block';
        document.getElementById('prof-id').value = data.id || '';
        document.getElementById('prof-fullname').value = data.fullName || '';
        document.getElementById('prof-nickname').value = data.nickname || '';
        document.getElementById('prof-level').value = data.level || '';
        document.getElementById('prof-school').value = data.school || '';
        document.getElementById('prof-phone').value = data.phone || '';
        document.getElementById('prof-parentName').value = data.parentName || '';
        document.getElementById('prof-parentPhone').value = data.parentPhone || '';
        document.getElementById('prof-address').value = data.address || '';
      } else {
        alert("ไม่พบข้อมูลผู้ใช้งาน: " + (data.message || "Unknown error"));
        window.location.href = 'main.php';
      }
    });
  }

  function saveStudentProfile() {
    const pass = document.getElementById('prof-password').value;
    const confirmPass = document.getElementById('prof-confirmPassword').value;

    if (pass || confirmPass) {
      if (pass !== confirmPass) {
        alert('รหัสผ่านใหม่ไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
        document.getElementById('prof-confirmPassword').focus();
        return;
      }
    }

    const profileData = {
      fullName: document.getElementById('prof-fullname').value,
      nickname: document.getElementById('prof-nickname').value,
      level: document.getElementById('prof-level').value,
      school: document.getElementById('prof-school').value,
      phone: document.getElementById('prof-phone').value,
      parentName: document.getElementById('prof-parentName').value,
      parentPhone: document.getElementById('prof-parentPhone').value,
      address: document.getElementById('prof-address').value,
      password: pass 
    };

    showLoading('กำลังบันทึกข้อมูลของคุณ...');

    callAPI('updateProfile', profileData).then(res => {
      hideLoading();
      if(res && res.success) {
        document.getElementById('prof-password').value = '';
        document.getElementById('prof-confirmPassword').value = '';

        var successModal = new bootstrap.Modal(document.getElementById('successProfileModal'));
        successModal.show();
      } else {
        alert('เกิดข้อผิดพลาด: ' + (res.message || 'Unknown Error'));
      }
    });
  }

  function showLoading(text) {
    const loadingOverlay = document.getElementById('fullPageLoading');
    if (loadingOverlay) {
      document.getElementById('loadingText').innerText = text;
      loadingOverlay.classList.add('show');
    }
  }

  function hideLoading() {
    const loadingOverlay = document.getElementById('fullPageLoading');
    if (loadingOverlay) loadingOverlay.classList.remove('show');
  }
</script>

</body>
</html>