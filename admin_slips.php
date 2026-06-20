<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// ==========================================
// API: จัดการข้อมูลสลิป (Backend)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0); // ป้องกัน Error HTML แทรกใน JSON
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        switch ($action) {
            
            // 1. ดึงรายการคอร์สทั้งหมดเพื่อแสดงในหน้าจัดการสลิป
            case 'getSlipsCourses':
                $stmt = $conn->prepare("SELECT course_id as id, name, level, duration FROM courses ORDER BY created_at DESC");
                $stmt->execute();
                $result = $stmt->get_result();
                
                $courses = [];
                while ($row = $result->fetch_assoc()) {
                    $courses[] = $row;
                }
                echo json_encode($courses);
                break;

            // 2. ดึงรายชื่อนักเรียนในคอร์ส เฉพาะคนที่อนุมัติแล้ว
            case 'getCourseStudentsForSlips':
                $courseId = $_GET['courseId'];
                
                // ค้นหานักเรียนใน enrollments และ join ข้อมูลจาก users, courses
                $sql = "SELECT e.enroll_id, 
                               u.user_id, 
                               CONCAT(u.full_name, ' (', IFNULL(u.nickname, ''), ')') as student_name,
                               e.payment_status,
                               e.approved_date,
                               e.slip_url,
                               e.paid_month,
                               e.payment_method,
                               (c.price + IFNULL(c.other_expense_price, 0)) as amount
                        FROM enrollments e
                        JOIN users u ON e.user_id = u.user_id
                        JOIN courses c ON e.course_id = c.course_id
                        WHERE e.course_id = ? 
                        AND e.approval_status IN ('approved', 'อนุมัติแล้ว')";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([$courseId]);
                $result = $stmt->get_result();
                
                $students = [];
                $thMonths = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

                while ($row = $result->fetch_assoc()) {
                    // แปลงรูปแบบวันที่และเวลาแบบไทย
                    $payDateStr = '-';
                    $payTimeStr = '-';
                    if (!empty($row['approved_date'])) {
                        $dt = new DateTime($row['approved_date']);
                        $m = (int)$dt->format('n');
                        $y = (int)$dt->format('Y') + 543;
                        $payDateStr = $dt->format('d') . ' ' . $thMonths[$m] . ' ' . $y;
                        $payTimeStr = $dt->format('H:i') . ' น.';
                    }

                    // ปรับสถานะการจ่ายเงินกรณีเป็นภาษาอังกฤษจากระบบเก่า
                    $paymentStatus = $row['payment_status'];
                    if ($paymentStatus === 'pending_payment') $paymentStatus = 'pending_payment';

                    $students[] = [
                        'enrollId' => $row['enroll_id'],
                        'studentId' => $row['user_id'],
                        'studentName' => $row['student_name'],
                        'paymentStatus' => $paymentStatus,
                        'payDate' => $payDateStr,
                        'payTime' => $payTimeStr,
                        'slipUrl' => $row['slip_url'] ?? '',
                        'amount' => $row['amount'] ?? '',
                        'paidMonth' => $row['paid_month'] ?? 'รายครั้ง',
                        'paymentMethod' => $row['payment_method'] ?? 'โอนเงิน'
                    ];
                }
                echo json_encode($students);
                break;

            // 3. ฟังก์ชันสำหรับแอดมิน เปลี่ยนสถานะการชำระเงิน (ปฏิเสธ/อนุมัติ)
            case 'updateSlipPaymentStatus':
                $enrollId = $data['enrollId'];
                $newStatus = $data['newStatus'];

                if ($newStatus === 'pending_payment') {
                    // ถ้าปฏิเสธ (เปลี่ยนเป็น pending_payment) ให้ล้างข้อมูลการชำระเงิน เพื่อให้ผู้ใช้อัปโหลดใหม่
                    $sql = "UPDATE enrollments 
                            SET payment_status = ?, approved_date = NULL, slip_url = NULL, payment_method = NULL 
                            WHERE enroll_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$newStatus, $enrollId]);
                } else {
                    // ถ้าอนุมัติ อัปเดตเฉพาะสถานะ (เก็บสลิปและข้อมูลไว้เหมือนเดิม)
                    $sql = "UPDATE enrollments SET payment_status = ? WHERE enroll_id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$newStatus, $enrollId]);
                }
                
                echo json_encode(['success' => true]);
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
  <title>Sci Math Academy - จัดการสลิป</title>
  
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
      <a href="admin_users.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-people-fill me-2"></i> จัดการผู้ใช้</a>
      <a href="admin_slips.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;">
        <i class="bi bi-receipt text-primary me-2"></i> จัดการสลิป
      </a>
      <a href="admin_news.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-megaphone-fill me-2"></i> ข่าวสาร</a>
    </div>
    
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5" style="background-color: #f4f6f9;">
    <div class="d-lg-none mb-3">
      <button class="btn btn-light btn-toggle-menu shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div id="adminSlipsCourseGridView">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <h4 class="fw-bold mb-0 text-dark">จัดการสลิปและข้อมูลการชำระเงิน</h4>
        <div class="input-group shadow-sm border-0 rounded-3 overflow-hidden" style="max-width: 350px;">
          <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" class="form-control bg-white border-0" id="searchSlipCourseInput" placeholder="ค้นหาชื่อคอร์ส..." onkeyup="filterSlipCourses(this.value)">
        </div>
      </div>
      
      <div class="row g-4" id="adminSlipsCourseGrid">
        <div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์ส...</div>
      </div>
    </div>
  </main>
</div>

<div id="slipFullPageLoading" class="full-page-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(255,255,255,0.8); z-index: 9999; flex-direction: column; justify-content: center; align-items: center;">
  <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
    <span class="visually-hidden">Loading...</span>
  </div>
  <h4 class="mt-3 fw-bold text-primary" id="slipLoadingText">กำลังประมวลผล...</h4>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'admin_slips.php';
  let allSlipsCourses = [];

  // --- API Call Helper ---
  async function callAPI(action, data = null, method = 'POST') {
    try {
      let url = `${API_URL}?action=${action}`;
      let options = { method: method };

      if (method === 'POST') {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = JSON.stringify(data);
      } else if (method === 'GET' && data) {
        const params = new URLSearchParams(data).toString();
        url += `&${params}`;
      }

      const response = await fetch(url, options);
      return await response.json();
    } catch (error) {
      console.error("API Error:", error);
      return { success: false, message: error.message };
    }
  }

  function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  document.addEventListener("DOMContentLoaded", function() {
    loadSlipCourses();
  });

  function loadSlipCourses() {
    const grid = document.getElementById('adminSlipsCourseGrid');
    grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์ส...</div>';
    
    callAPI('getSlipsCourses', null, 'GET').then(function(courses) {
      if(courses && !courses.message) {
        allSlipsCourses = courses;
        displaySlipCoursesGrid(allSlipsCourses);
      } else {
        grid.innerHTML = `<div class="col-12 text-center py-5 text-danger"><i class="bi bi-exclamation-triangle fs-1"></i><br>โหลดข้อมูลไม่สำเร็จ: ${courses.message || 'Unknown Error'}</div>`;
      }
    });
  }

  // เปลี่ยนเส้นทางไปหน้า admin_slip_students.php พร้อมส่งค่าไป
  function viewSlipStudents(courseId, courseName, duration) {
    const safeName = encodeURIComponent(courseName);
    const safeDuration = encodeURIComponent(duration);
    window.location.href = `admin_slip_students.php?courseId=${courseId}&courseName=${safeName}&duration=${safeDuration}`;
  }

  function displaySlipCoursesGrid(courses) {
    const grid = document.getElementById('adminSlipsCourseGrid');
    grid.innerHTML = '';

    if (!courses || courses.length === 0) {
      grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">ไม่พบข้อมูลคอร์สเรียน</div>';
      return;
    }

    courses.forEach(course => {
      let safeCourseName = course.name ? String(course.name).replace(/'/g, "\\'").replace(/"/g, '"') : '';
      let safeDuration = course.duration ? String(course.duration).replace(/'/g, "\\'") : '';

      grid.innerHTML += `
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative" style="cursor: pointer;" onclick="viewSlipStudents('${course.id}', '${safeCourseName}', '${safeDuration}')">
            <div class="position-absolute top-0 start-0 w-100" style="background-color: #2b4d7e; height: 5px;"></div>
            <div class="card-body p-4 d-flex flex-column text-center align-items-center">
              <div class="mb-3 mt-2"><i class="bi bi-folder2-open text-primary" style="font-size: 3rem;"></i></div>
              <span class="badge bg-light text-dark border px-3 py-2 rounded-pill mb-2">${course.level}</span>
              <h5 class="fw-bold mb-3 text-dark">${course.name}</h5>
              <button class="btn w-100 fw-bold mt-auto" style="background-color: #e6f0ff; color: #1e40af; border-radius: 8px;">
                <i class="bi bi-people-fill me-1"></i> ดูรายชื่อนักเรียน
              </button>
            </div>
          </div>
        </div>
      `;
    });
  }

  function filterSlipCourses(keyword) {
    keyword = String(keyword).toLowerCase().trim();
    const filtered = allSlipsCourses.filter(c => String(c.name).toLowerCase().includes(keyword));
    displaySlipCoursesGrid(filtered);
  }
</script>
</body>
</html>