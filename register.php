<?php
require_once 'db.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

// ==========================================
// API: จัดการข้อมูลการลงทะเบียน (Backend)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        switch ($action) {
            case 'getCourses':
                $stmt = $conn->prepare("SELECT course_id as id, name, level, price, details FROM courses WHERE deleted_at IS NULL ORDER BY created_at DESC");
                $stmt->execute();
                $result = $stmt->get_result();
                $courses = [];
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
                echo json_encode($courses);
                break;

            // 2. บันทึกข้อมูลการลงทะเบียน
            case 'registerUser':
                $fullName = trim($data['fullName']);
                $phone = trim($data['phone']);

                // เช็คชื่อหรือเบอร์โทรซ้ำ (ไม่ให้สมัครซ้ำ)
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE full_name = ? OR phone = ?");
                $stmt->execute([$fullName, $phone]);
                $res = $stmt->get_result();
                if ($res->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'ชื่อ-นามสกุล หรือ เบอร์โทรศัพท์นี้ถูกลงทะเบียนในระบบแล้ว']);
                    exit;
                }

                // สร้างรหัส Student ID (ST-XXXX) อัตโนมัติ
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE 'ST-%' ORDER BY CAST(SUBSTRING(user_id, 4) AS UNSIGNED) DESC LIMIT 1");
                $stmt->execute();
                $res = $stmt->get_result();
                $nextIdNum = 1;
                if ($res->num_rows > 0) {
                    $lastId = $res->fetch_assoc()['user_id'];
                    $nextIdNum = intval(substr($lastId, 3)) + 1;
                }
                $newStudentId = 'ST-' . str_pad($nextIdNum, 4, '0', STR_PAD_LEFT);

                // เข้ารหัสผ่าน
                $passHash = password_hash($data['password'], PASSWORD_DEFAULT);

                // บันทึกข้อมูลนักเรียนใหม่
                $sql = "INSERT INTO users (user_id, full_name, nickname, grade, school, phone, parent_name, parent_phone, address, role, password_hash) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'student', ?)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $newStudentId, $fullName, $data['nickname'], $data['grade'], 
                    $data['school'], $phone, $data['parentName'], $data['parentPhone'], 
                    $data['address'], $passHash
                ]);

                // บันทึกข้อมูลคอร์สเรียนลงชีต Enrollments (ถ้าเลือกคอร์สมาด้วย)
                if (!empty($data['courses']) && is_array($data['courses'])) {
                    $insStmt = $conn->prepare("INSERT INTO enrollments (enroll_id, user_id, course_id, approval_status, payment_status) VALUES (?, ?, ?, 'pending_approval', 'pending_payment')");
                    foreach ($data['courses'] as $cId) {
                        $enrollId = 'EN' . time() . rand(10, 99);
                        $insStmt->execute([$enrollId, $newStudentId, $cId]);
                    }
                }

                echo json_encode(['success' => true, 'message' => 'ลงทะเบียนสำเร็จ']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                break;
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
  <title>Sci Math Academy - ลงทะเบียน</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .card-container { max-width: 800px; margin: 2rem auto; }
    .btn-custom { transition: 0.2s; }
    .btn-custom:hover { opacity: 0.9; transform: translateY(-2px); }
  </style>
</head>
<body>

<div class="container pb-5">
  
  <div id="fullPageLoading" class="d-none position-fixed top-0 start-0 w-100 h-100 bg-white bg-opacity-75" style="z-index: 9999; backdrop-filter: blur(2px);">
    <div class="d-flex flex-column justify-content-center align-items-center h-100">
      <div class="spinner-border" style="width: 3rem; height: 3rem; color: #2b4d7e;" role="status"></div>
      <h5 class="mt-3 fw-bold" style="color: #2b4d7e;">กำลังตรวจสอบและบันทึกข้อมูล...</h5>
    </div>
  </div>

  <div id="view-register" class="card card-container card-register border-0 shadow-lg rounded-4 overflow-hidden">
    <div class="card-header d-flex justify-content-between align-items-center py-4 bg-white border-bottom pt-4 px-4">
      <h4 class="mb-0 fw-bold" style="color: #2b4d7e;"><i class="bi bi-person-plus-fill me-2"></i>ลงทะเบียนนักเรียนใหม่</h4>
      <button type="button" class="btn-close" onclick="window.location.href='index.php'" aria-label="Close"></button>
    </div>
    <div class="card-body p-4 px-md-5 bg-white">
      
      <div id="registerError" class="alert alert-danger d-none small py-3 mb-4 rounded-3 border-0 bg-danger bg-opacity-10 text-danger" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><span id="registerErrorText"></span>
      </div>

      <form id="registerForm" onsubmit="handleRegister(event)">
        
        <div class="row mb-3">
          <div class="col-12">
            <label class="form-label text-muted small fw-bold">ชื่อ-สกุล <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="fullName" placeholder="ไม่ต้องใส่คำนำหน้าชื่อ" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6 mb-3 mb-md-0">
            <label class="form-label text-muted small fw-bold">ชื่อเล่น <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="nickname" required>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold">เบอร์โทรศัพท์ (นักเรียน) <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" name="phone" placeholder="08xxxxxxxx" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6 mb-3 mb-md-0">
            <label class="form-label text-muted small fw-bold">ระดับชั้น <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="grade" placeholder="เช่น ม.4" required>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold">โรงเรียน <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="school" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-12">
            <label class="form-label text-muted small fw-bold">ชื่อผู้ปกครอง <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="parentName" required>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-12">
            <label class="form-label text-muted small fw-bold">เบอร์โทรศัพท์ผู้ปกครอง <span class="text-danger">*</span></label>
            <input type="tel" class="form-control" name="parentPhone" placeholder="08xxxxxxxx" required>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-12">
            <label class="form-label text-muted small fw-bold">ที่อยู่ <span class="text-danger">*</span></label>
            <textarea class="form-control" name="address" rows="2" required></textarea>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-md-6 mb-3 mb-md-0">
            <label class="form-label text-muted small fw-bold">ตั้งรหัสผ่าน <span class="text-danger">*</span></label>
            <div class="position-relative">
              <input type="password" class="form-control pe-5" name="password" id="regPassword" required>
              <i class="bi bi-eye text-muted position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem;" onclick="togglePasswordVisibility('regPassword', this)"></i>
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
            <div class="position-relative">
              <input type="password" class="form-control pe-5" id="regConfirmPassword" required>
              <i class="bi bi-eye text-muted position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem;" onclick="togglePasswordVisibility('regConfirmPassword', this)"></i>
            </div>
            <div id="passwordMismatch" class="text-danger small mt-1 fw-bold" style="display: none;"><i class="bi bi-x-circle me-1"></i>รหัสผ่านไม่ตรงกัน</div>
          </div>
        </div>

        <div class="p-4 rounded-4 mb-4" style="background-color: #f8faff; border: 1px solid #e2e8f0;">
          <h6 class="mb-3 fw-bold text-dark"><i class="bi bi-journal-check me-2 text-primary" style="color: #2b4d7e !important;"></i>เลือกคอร์สเรียน <span class="text-muted fw-normal small">(เลือกได้มากกว่า 1)</span></h6>
          <div id="courseListContainer" class="d-flex flex-column gap-2">
            <div class="text-center text-muted small py-4">กำลังโหลดข้อมูลคอร์สเรียน <div class="spinner-border spinner-border-sm ms-2" style="color: #2b4d7e;" role="status"></div></div>
          </div>
        </div>

        <button type="submit" class="btn btn-custom w-100 fs-6 py-3 fw-bold shadow-sm" id="btnRegister" style="border-radius: 12px; background-color: #2b4d7e; color: white;">ยืนยันการลงทะเบียนเรียน</button>
      </form>
    </div>
  </div>

  <div id="view-success" class="card card-container text-center py-5 d-none border-0 shadow-lg rounded-4">
    <div class="card-body px-4">
      <div class="mb-4">
        <i class="bi bi-check-circle-fill text-success" style="font-size: 70px;"></i>
      </div>
      <h3 class="mb-3 fw-bold text-dark">สมัครสมาชิกสำเร็จ!</h3>
      <p class="text-muted mb-4">ข้อมูลของคุณถูกบันทึกเรียบร้อยแล้ว<br>กรุณาสแกน QR Code ด้านล่างเพื่อเข้า Line กลุ่ม</p>
      
      <div class="p-3 bg-white rounded-4 d-inline-block mb-4 shadow-sm border">
        <img src="img/line.jpg" style="width: 160px; height: 160px; object-fit: contain;"> 
        <div class="mt-3 fs-5 fw-bold" style="color: #00b900;"><i class="bi bi-line me-2"></i>@SciMathLine</div>
      </div>
      
      <div class="mx-auto mt-2" style="max-width: 280px;">
        <button class="btn w-100 py-2 fw-bold text-white" style="background-color: #2b4d7e; border-radius: 12px;" onclick="window.location.href='index.php'">กลับไปหน้าเข้าสู่ระบบ</button>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'register.php';

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
    loadCourses();

    const regForm = document.getElementById('registerForm');
    if(regForm) {
      regForm.addEventListener('input', function() {
        const pass = document.getElementById('regPassword').value;
        const confirmPass = document.getElementById('regConfirmPassword').value;
        const mismatchWarning = document.getElementById('passwordMismatch');
        if(confirmPass && pass !== confirmPass) {
          mismatchWarning.style.display = 'block';
          document.getElementById('regConfirmPassword').classList.add('is-invalid');
        } else {
          mismatchWarning.style.display = 'none';
          document.getElementById('regConfirmPassword').classList.remove('is-invalid');
        }
      });
    }
  });

  function loadCourses() {
    const container = document.getElementById('courseListContainer');
    callAPI('getCourses', null, 'GET').then(function(courses) {
      container.innerHTML = '';
      if(!courses || courses.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-3">ยังไม่มีคอร์สเรียนเปิดรับสมัคร</div>';
        return;
      }
      
      courses.forEach(function(course, index) {
        const courseIdValue = course.id; 
        
        container.innerHTML += `
          <label class="w-100 p-3 bg-white border rounded-3 shadow-sm" for="course${index}" style="cursor: pointer; transition: all 0.2s;" onmouseover="this.style.borderColor='#2b4d7e'" onmouseout="this.style.borderColor='#dee2e6'">
            <div class="d-flex justify-content-between align-items-center">
              <div class="form-check m-0 d-flex align-items-center">
                <input class="form-check-input fs-5 mt-0 me-3 course-checkbox" type="checkbox" value="${courseIdValue}" id="course${index}" style="cursor: pointer;">
                <div>
                  <span class="fw-bold text-dark d-block" style="font-size: 1.05rem;">${course.name}</span>
                  <span class="badge bg-light text-dark border mt-1 px-2 py-1">ระดับชั้น: ${course.level}</span>
                </div>
              </div>
              <div class="fw-bold fs-5" style="color: #2b4d7e;">${Number(course.price).toLocaleString()} ฿</div>
            </div>
          </label>
        `;
      });
    });
  }

  function handleRegister(e) {
    e.preventDefault();
    const pass = document.getElementById('regPassword').value;
    const confirmPass = document.getElementById('regConfirmPassword').value;
    const errorDiv = document.getElementById('registerError');
    const errorText = document.getElementById('registerErrorText');
    const loadingOverlay = document.getElementById('fullPageLoading');
    const btn = document.getElementById('btnRegister');
    
    errorDiv.classList.add('d-none');

    if(pass !== confirmPass) {
      document.getElementById('regConfirmPassword').focus();
      return;
    }

    loadingOverlay.classList.remove('d-none');
    btn.disabled = true;

    const form = document.getElementById('registerForm');
    const coursesChecked = Array.from(document.querySelectorAll('.course-checkbox:checked')).map(cb => cb.value);

    const data = {
      fullName: form.fullName.value.trim(),
      nickname: form.nickname.value.trim(),
      grade: form.grade.value.trim(),
      school: form.school.value.trim(),
      phone: form.phone.value.trim(),
      password: form.password.value,
      parentName: form.parentName.value.trim(),
      parentPhone: form.parentPhone.value.trim(),
      address: form.address.value.trim(),
      courses: coursesChecked
    };

    callAPI('registerUser', data).then(function(response) {
      loadingOverlay.classList.add('d-none');
      btn.disabled = false;
      
      if(response.success) {
        form.reset();
        document.getElementById('view-register').classList.add('d-none');
        document.getElementById('view-success').classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } else {
        errorText.innerText = response.message;
        errorDiv.classList.remove('d-none');
        document.getElementById('view-register').scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  }
</script>

</body>
</html>