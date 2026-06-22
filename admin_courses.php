<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// ==========================================
// API: จัดการข้อมูลคอร์สเรียนและนักเรียน (รวมไว้ที่นี่ทั้งหมด)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        switch ($action) {
            case 'getAllCourses':
                $stmt = $conn->prepare("SELECT * FROM courses WHERE deleted_at IS NULL ORDER BY created_at DESC");
                $stmt->execute();
                $result = $stmt->get_result();
                
                $courses = [];
                while ($row = $result->fetch_assoc()) {
                    $courses[] = [
                        'id' => $row['course_id'],
                        'name' => $row['name'],
                        'level' => $row['level'],
                        'price' => $row['price'],
                        'details' => $row['details'],
                        'otherExpenseName' => $row['other_expense_name'] ?? '',
                        'otherExpensePrice' => $row['other_expense_price'] ?? 0,
                        'days' => $row['days'] ?? '',
                        'time' => $row['time'] ?? '',
                        'type' => $row['course_type'] ?? 'คอร์สตัวต่อตัว',
                        'month' => $row['course_month'] ?? '',
                        'yearBE' => $row['year_be'] ?? '',
                        'duration' => $row['duration'] ?? ''
                    ];
                }
                echo json_encode($courses);
                break;

            case 'saveCourse':
                if (!empty($data['id'])) {
                    $sql = "UPDATE courses SET 
                            name=?, level=?, price=?, details=?, 
                            other_expense_name=?, other_expense_price=?, days=?, 
                            time=?, course_type=?, course_month=?, year_be=?, duration=? 
                            WHERE course_id=?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $data['name'], $data['level'], $data['price'], $data['details'],
                        $data['otherExpenseName'] ?? null, $data['otherExpensePrice'] ?? 0, $data['days'] ?? null,
                        $data['time'] ?? null, $data['type'] ?? 'คอร์สตัวต่อตัว', $data['month'] ?? null, $data['yearBE'] ?? null, $data['duration'] ?? null,
                        $data['id']
                    ]);
                    echo json_encode(['success' => true, 'message' => 'อัปเดตคอร์สสำเร็จ']);
                } else {
                    $newId = 'C' . substr(time(), -5);
                    $sql = "INSERT INTO courses (
                                course_id, name, level, price, details, 
                                other_expense_name, other_expense_price, days, 
                                time, course_type, course_month, year_be, duration
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([
                        $newId, $data['name'], $data['level'], $data['price'], $data['details'],
                        $data['otherExpenseName'] ?? null, $data['otherExpensePrice'] ?? 0, $data['days'] ?? null,
                        $data['time'] ?? null, $data['type'] ?? 'คอร์สตัวต่อตัว', $data['month'] ?? null, $data['yearBE'] ?? null, $data['duration'] ?? null
                    ]);
                    echo json_encode(['success' => true, 'message' => 'เพิ่มคอร์สใหม่สำเร็จ']);
                }
                break;

            case 'deleteCourse':
                $courseId = $data['courseId'];
                $stmt = $conn->prepare("UPDATE courses SET deleted_at = CURRENT_TIMESTAMP WHERE course_id = ?");
                $stmt->execute([$courseId]);
                echo json_encode(['success' => true, 'message' => 'ลบคอร์สสำเร็จ']);
                break;

            case 'getCourseStudents':
                $courseId = $_GET['courseId'];
                // เพิ่มเงื่อนไข AND e.deleted_at IS NULL และ u.deleted_at IS NULL
                $sql = "SELECT u.user_id, u.full_name, u.nickname, u.grade, u.school, u.phone, e.approval_status 
                        FROM users u 
                        JOIN enrollments e ON u.user_id = e.user_id 
                        WHERE e.course_id = ? AND u.role != 'admin' AND e.deleted_at IS NULL AND u.deleted_at IS NULL";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$courseId]);
                $result = $stmt->get_result();

                $students = [];
                while ($row = $result->fetch_assoc()) {
                    $students[] = [
                        'id' => $row['user_id'],
                        'fullName' => $row['full_name'],
                        'nickname' => $row['nickname'],
                        'grade' => $row['grade'],
                        'school' => $row['school'],
                        'phone' => $row['phone'],
                        'status' => $row['approval_status']
                    ];
                }
                echo json_encode($students);
                break;

            case 'updateStudentCourseStatus':
                $userId = $data['rowId'];
                $courseId = $data['courseId'];
                $newStatus = $data['newStatus'];

                $stmt = $conn->prepare("UPDATE enrollments SET approval_status = ? WHERE user_id = ? AND course_id = ?");
                $stmt->execute([$newStatus, $userId, $courseId]);
                echo json_encode(['success' => true]);
                break;

            case 'getAvailableStudents':
                $courseId = $_GET['courseId'];
                // เพิ่มเงื่อนไข AND deleted_at IS NULL เพื่อไม่ดึงนักเรียนที่โดนลบมาแสดง
                $sql = "SELECT user_id, full_name, nickname, grade, school, phone 
                        FROM users 
                        WHERE role != 'admin' AND deleted_at IS NULL
                        AND user_id NOT IN (SELECT user_id FROM enrollments WHERE course_id = ? AND deleted_at IS NULL)";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$courseId]);
                $result = $stmt->get_result();

                $availableStudents = [];
                while ($row = $result->fetch_assoc()) {
                    $availableStudents[] = [
                        'id' => $row['user_id'],
                        'fullName' => $row['full_name'],
                        'nickname' => $row['nickname'],
                        'grade' => $row['grade'],
                        'school' => $row['school'],
                        'phone' => $row['phone']
                    ];
                }
                echo json_encode($availableStudents);
                break;

            case 'addMultipleStudentsToCourse':
                $userIds = $data['rowIds'];
                $courseId = $data['courseId'];

                // 1. ดึงข้อมูล course_month และ year_be จากตาราง courses
                $stmtCourse = $conn->prepare("SELECT course_month, year_be FROM courses WHERE course_id = ?");
                $stmtCourse->execute([$courseId]);
                $courseResult = $stmtCourse->get_result();
                $courseData = $courseResult->fetch_assoc();
                
                // จัดรูปแบบ paid_month (เช่น "กค 2569")
                $paidMonthFormat = null;
                if ($courseData) {
                    $month = trim($courseData['course_month'] ?? '');
                    $year = trim($courseData['year_be'] ?? '');
                    if ($month !== '' || $year !== '') {
                        $paidMonthFormat = trim($month . ' ' . $year);
                    }
                }

                // 2. Insert ข้อมูลลง enrollments พร้อม paid_month ที่ตั้งค่าไว้
                $stmt = $conn->prepare("INSERT INTO enrollments (enroll_id, user_id, course_id, approval_status, payment_status, paid_month) VALUES (?, ?, ?, 'approved', 'pending_payment', ?)");
                
                foreach ($userIds as $userId) {
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND course_id = ? AND deleted_at IS NULL");
                    $checkStmt->execute([$userId, $courseId]);
                    $checkResult = $checkStmt->get_result();
                    $row = $checkResult->fetch_row();
                    
                    if ($row[0] == 0) {
                        $enrollId = 'E' . uniqid();
                        $stmt->execute([$enrollId, $userId, $courseId, $paidMonthFormat]);
                    }
                }
                echo json_encode(['success' => true]);
                break;

            // ==========================================
            // รับคำสั่ง Soft Delete นักเรียนออกจากคอร์ส
            // ==========================================
            case 'softDeleteStudentCourse':
                $userId = $data['userId'] ?? null;
                $courseId = $data['courseId'] ?? null;

                if (!$userId || !$courseId) {
                    echo json_encode(['success' => false, 'message' => 'ส่งข้อมูลไม่ครบถ้วน (ต้องการ userId และ courseId)']);
                    break;
                }

                $thaiMonths = [
                    'มค' => 1, 'กพ' => 2, 'มีค' => 3, 'เมย' => 4, 'พค' => 5, 'มิย' => 6,
                    'กค' => 7, 'สค' => 8, 'กย' => 9, 'ตค' => 10, 'พย' => 11, 'ธค' => 12
                ];
                
                $currentMonthNum = (int)date('n'); 

                // ดึงรายการที่ยังไม่ถูกลบ
                $stmt = $conn->prepare("SELECT enroll_id, paid_month, payment_status FROM enrollments WHERE user_id = ? AND course_id = ? AND deleted_at IS NULL");
                $stmt->execute([$userId, $courseId]);
                $result = $stmt->get_result();
                $enrollments = $result->fetch_all(MYSQLI_ASSOC); 

                $deletedCount = 0;

                foreach ($enrollments as $enroll) {
                    $enrollId = $enroll['enroll_id'];
                    $paidMonthStr = trim($enroll['paid_month'] ?? '');
                    $paymentStatus = $enroll['payment_status'];
                    
                    $shouldDelete = false;

                    if (array_key_exists($paidMonthStr, $thaiMonths)) {
                        $enrollMonthNum = $thaiMonths[$paidMonthStr];
                        if ($enrollMonthNum >= $currentMonthNum) {
                            $shouldDelete = true;
                        } else {
                            if ($paymentStatus !== 'approval_payment') {
                                $shouldDelete = true;
                            }
                        }
                    } else {
                        // ไม่ระบุเดือนก็ลบเมื่อยังไม่จ่ายเงิน
                        if ($paymentStatus !== 'approval_payment') {
                            $shouldDelete = true;
                        }
                    }

                    if ($shouldDelete) {
                        $delStmt = $conn->prepare("UPDATE enrollments SET deleted_at = CURRENT_TIMESTAMP WHERE enroll_id = ?");
                        $delStmt->execute([$enrollId]);
                        $deletedCount++;
                    }
                }
                echo json_encode(['success' => true, 'message' => "ลบข้อมูลสำเร็จ ($deletedCount รายการ)"]);
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
  <title>Sci Math Academy - จัดการคอร์สเรียน</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/light.css">

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
      <a href="admin_courses.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;">
        <i class="bi bi-journal-bookmark text-primary me-2"></i> จัดการคอร์ส
      </a>
      <a href="admin_users.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-people-fill me-2"></i> จัดการผู้ใช้</a>
      <a href="admin_slips.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-receipt me-2"></i> จัดการสลิป</a>
      <a href="admin_news.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-megaphone-fill me-2"></i> ข่าวสาร</a>
    </div>
    
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
      <div class="d-flex align-items-center">
        <button class="btn btn-light btn-toggle-menu me-3 shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
      </div>
    </div>

    <div id="courseGridView">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h4 class="fw-bold mb-0 text-dark">จัดการคอร์สเรียน</h4>
        <button class="btn text-white fw-bold px-4 shadow-sm" style="background-color: #2b4d7e; border-radius: 8px;" onclick="openCourseForm()">
          <i class="bi bi-plus-lg me-1"></i> เพิ่มคอร์สใหม่
        </button>
      </div>
      
      <div class="row g-4" id="courseGrid">
        <div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์ส...</div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="courseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
          <h5 class="modal-title fw-bold text-dark" id="courseModalTitle">เพิ่มคอร์สเรียนใหม่</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <form id="courseForm">
            <input type="hidden" id="courseId">
            <div class="row">
              <div class="col-md-12 mb-3">
                <label class="form-label small fw-bold text-muted">ชื่อคอร์สเรียน <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg bg-light border-0" id="courseName" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-bold text-muted">ระดับชั้น <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-lg bg-light border-0" id="courseLevel" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label small fw-bold text-muted">ราคา (บาท) <span class="text-danger">*</span></label>
                <input type="number" class="form-control form-control-lg bg-light border-0" id="coursePrice" required>
              </div>
            </div>

            <div class="row p-3 bg-white border rounded-3 mx-0 mb-3">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-clock-history me-2 text-primary"></i>รูปแบบและระยะเวลาคอร์ส</h6>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-bold text-muted">รูปแบบคอร์ส</label>
                <select class="form-select bg-light border-0" id="courseType">
                  <option value="คอร์สตัวต่อตัว">คอร์สตัวต่อตัว</option>
                  <option value="คอร์สกลุ่ม">คอร์สกลุ่ม</option>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-bold text-muted">คอร์สประจำเดือน</label>
                <select class="form-select bg-light border-0" id="courseMonth">
                  <option value="มค">มกราคม (มค)</option>
                  <option value="กพ">กุมภาพันธ์ (กพ)</option>
                  <option value="มีค">มีนาคม (มีค)</option>
                  <option value="เมย">เมษายน (เมย)</option>
                  <option value="พค">พฤษภาคม (พค)</option>
                  <option value="มิย">มิถุนายน (มิย)</option>
                  <option value="กค">กรกฎาคม (กค)</option>
                  <option value="สค">สิงหาคม (สค)</option>
                  <option value="กย">กันยายน (กย)</option>
                  <option value="ตค">ตุลาคม (ตค)</option>
                  <option value="พย">พฤศจิกายน (พย)</option>
                  <option value="ธค">ธันวาคม (ธค)</option>
                </select>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label small fw-bold text-muted">เลือกพศ.</label>
                <select class="form-select bg-light border-0" id="courseYearBE"></select>
              </div>
              <div class="col-md-12 mb-3">
                <label class="form-label small fw-bold text-muted">ระยะคอร์ส (เริ่ม - สิ้นสุด)</label>
                <div class="input-group">
                  <input type="text" class="form-control bg-light border-0" id="courseStartDate" placeholder="เลือกวันเริ่มต้น">
                  <span class="input-group-text border-0 bg-light">-</span>
                  <input type="text" class="form-control bg-light border-0" id="courseEndDate" placeholder="เลือกวันสิ้นสุด">
                </div>
              </div>
            </div>

            <div class="row p-3 bg-white border rounded-3 mx-0 mb-3">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-calendar-event me-2 text-primary"></i>กำหนดการเรียน</h6>
              <div class="col-12 mb-3">
                <label class="form-label small fw-bold text-muted">วันเรียน (เลือกได้หลายวัน)</label>
                <div class="d-flex flex-wrap gap-2">
                  <input type="checkbox" class="btn-check course-day-cb" id="dayMon" value="จันทร์">
                  <label class="btn btn-outline-primary rounded-pill px-3" for="dayMon">จันทร์</label>
                  <input type="checkbox" class="btn-check course-day-cb" id="dayTue" value="อังคาร">
                  <label class="btn btn-outline-primary rounded-pill px-3" for="dayTue">อังคาร</label>
                  <input type="checkbox" class="btn-check course-day-cb" id="dayWed" value="พุธ">
                  <label class="btn btn-outline-primary rounded-pill px-3" for="dayWed">พุธ</label>
                  <input type="checkbox" class="btn-check course-day-cb" id="dayThu" value="พฤหัสบดี">
                  <label class="btn btn-outline-primary rounded-pill px-3" for="dayThu">พฤหัสบดี</label>
                  <input type="checkbox" class="btn-check course-day-cb" id="dayFri" value="ศุกร์">
                  <label class="btn btn-outline-primary rounded-pill px-3" for="dayFri">ศุกร์</label>
                  <input type="checkbox" class="btn-check course-day-cb" id="daySat" value="เสาร์">
                  <label class="btn btn-outline-primary rounded-pill px-3" for="daySat">เสาร์</label>
                  <input type="checkbox" class="btn-check course-day-cb" id="daySun" value="อาทิตย์">
                  <label class="btn btn-outline-primary rounded-pill px-3" for="daySun">อาทิตย์</label>
                </div>
              </div>
              <div class="col-md-6 mb-3 mb-md-0">
                <label class="form-label small fw-bold text-muted">เวลาเริ่มเรียน</label>
                <input type="time" class="form-control bg-light border-0" id="courseTimeStart">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">เวลาเลิกเรียน</label>
                <input type="time" class="form-control bg-light border-0" id="courseTimeEnd">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-bold text-muted">รายละเอียดเพิ่มเติม</label>
              <textarea class="form-control bg-light border-0" id="courseDetails" rows="3"></textarea>
            </div>

            <div class="row mb-4 p-3 bg-white border rounded-3 mx-0">
              <h6 class="fw-bold text-dark mb-3"><i class="bi bi-tags me-2 text-primary"></i>บริการเสริม / ค่าใช้จ่ายอื่นๆ (ถ้ามี)</h6>
              <div class="col-md-6 mb-3 mb-md-0">
                <label class="form-label small fw-bold text-muted">ชื่อค่าใช้จ่าย (เช่น ค่าหนังสือ)</label>
                <input type="text" class="form-control bg-light border-0" id="courseOtherExpenseName" placeholder="ระบุชื่อค่าใช้จ่าย">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-bold text-muted">ราคา (บาท)</label>
                <input type="number" class="form-control bg-light border-0" id="courseOtherExpensePrice" placeholder="0">
              </div>
            </div>

            <button type="button" class="btn text-white w-100 py-2 fw-bold" style="background-color: #2b4d7e; border-radius: 8px;" onclick="saveCourse()">บันทึกข้อมูลคอร์ส</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="validationModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg rounded-4">
        <div class="modal-body text-center p-5">
          <i class="bi bi-exclamation-triangle text-warning mb-4" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mb-2">ข้อมูลไม่ครบถ้วน</h5>
          <p class="text-muted">กรุณากรอก <b>ชื่อคอร์สเรียน, ระดับชั้น</b> และ <b>ราคา</b> ให้ครบถ้วน</p>
          <div class="mt-4">
            <button type="button" class="btn btn-warning px-4 fw-bold text-dark" data-bs-dismiss="modal" style="border-radius: 8px;">ตกลง</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="deleteCourseConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-body text-center p-5">
          <i class="bi bi-exclamation-circle text-danger mb-4" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mb-2">ยืนยันการลบคอร์สเรียน</h5>
          <p class="text-muted">คุณต้องการลบคอร์ส <span id="delCourseLabelName" class="fw-bold text-dark"></span> ใช่หรือไม่?</p>
          <input type="hidden" id="delCourseTargetId">
          <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
            <button type="button" class="btn btn-danger px-4" onclick="confirmDeleteCourse()" style="border-radius: 8px;">ใช่, ลบคอร์ส</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="fullPageLoading" class="full-page-overlay">
    <div class="spinner-border text-secondary" style="width: 4rem; height: 4rem;" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3 fw-bold text-secondary" id="loadingText">กำลังประมวลผล...</h4>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/th.js"></script>

<script>
  const API_URL = 'admin_courses.php';

  let allCoursesList = [];
  let courseModalInstance;
  let deleteCourseModalInstance;
  let startPicker, endPicker;

  function toggleSidebar() {
    document.getElementById('adminSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

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

  const updateYearBE = function(selectedDates, dateStr, instance) {
    if(instance && instance.currentYearElement) {
        instance.currentYearElement.value = instance.currentYear + 543;
    }
  };

  function initFlatpickrBE() {
    const config = {
      disableMobile: true,
      locale: "th",
      dateFormat: "d/m/Y",
      allowInput: true,
      formatDate: (date, format, locale) => {
        let yearBE = date.getFullYear() + 543;
        let month = String(date.getMonth() + 1).padStart(2, '0');
        let day = String(date.getDate()).padStart(2, '0');
        return `${day}/${month}/${yearBE}`;
      },
      parseDate: (dateStr, format) => {
        if (!dateStr) return null;
        let parts = dateStr.split('/');
        if (parts.length === 3) {
          let day = parseInt(parts[0]);
          let month = parseInt(parts[1]) - 1;
          let year = parseInt(parts[2]);
          if (year > 2400) year -= 543; 
          return new Date(year, month, day);
        }
        return new Date(dateStr);
      },
      onReady: function(d, s, inst) {
        if(inst.currentYearElement) {
            inst.currentYearElement.setAttribute('readonly', 'readonly');
        }
        updateYearBE(d, s, inst);
      },
      onOpen: updateYearBE,
      onYearChange: updateYearBE,
      onMonthChange: updateYearBE,
      onValueUpdate: updateYearBE
    };

    startPicker = flatpickr("#courseStartDate", config);
    endPicker = flatpickr("#courseEndDate", config);
  }

  document.addEventListener("DOMContentLoaded", function() {
    initFlatpickrBE();
    loadCoursesData();
  });

  function setupYearMonthOptions() {
    const yearSelect = document.getElementById('courseYearBE');
    if (yearSelect.options.length === 0) {
      const currentYear = new Date().getFullYear();
      const currentYearBE = currentYear > 2500 ? currentYear : currentYear + 543;
      for (let i = currentYearBE - 1; i <= currentYearBE + 5; i++) {
        let option = document.createElement('option');
        option.value = i;
        option.text = i;
        yearSelect.appendChild(option);
      }
    }
  }

  function setDefaultMonthYear() {
    const monthNames = ["มค", "กพ", "มีค", "เมย", "พค", "มิย", "กค", "สค", "กย", "ตค", "พย", "ธค"];
    const currentMonthIndex = new Date().getMonth();
    document.getElementById('courseMonth').value = monthNames[currentMonthIndex];
    
    const currentYear = new Date().getFullYear();
    const currentYearBE = currentYear > 2500 ? currentYear : currentYear + 543;
    document.getElementById('courseYearBE').value = currentYearBE;
  }

  function loadCoursesData() {
    const grid = document.getElementById('courseGrid');
    grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์ส...</div>';
    
    callAPI('getAllCourses', null, 'GET').then(function(courses) {
      allCoursesList = courses;
      displayCourseGrid(allCoursesList);
    });
  }

  function goToStudents(id, name) {
    window.location.href = `admin_course_students.php?courseId=${id}&courseName=${encodeURIComponent(name)}`;
  }

  function displayCourseGrid(courses) {
    const grid = document.getElementById('courseGrid');
    grid.innerHTML = '';

    if (!courses || courses.length === 0) {
      grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">ยังไม่มีคอร์สเรียนในระบบ</div>';
      return;
    }

    courses.forEach(course => {
      let detailsText = course.details ? course.details : 'ไม่มีรายละเอียดเพิ่มเติม';
      let otherExpenseHtml = '';
      if (course.otherExpenseName && course.otherExpensePrice > 0) {
        otherExpenseHtml = `
        <span class="text-muted small">ค่าใช้จ่ายอื่นๆ</span>
          <div class="d-flex justify-content-between align-items-center mb-0 p-2 rounded-3 mt-2" style="background-color: #e2e8f0; border-left: 3px solid #64748b;">
            <span class="text-muted small fw-bold">${course.otherExpenseName}</span>
            <span class="fw-bold fs-6 text-secondary">+${Number(course.otherExpensePrice).toLocaleString()} ฿</span>
          </div>
        `;
      }

      let displayDays = course.days ? course.days : '-';
      let displayTime = course.time ? course.time : '-';
      let monthText = course.month ? course.month : '-';
      let yearText = course.yearBE ? course.yearBE : '-';
      let typeDisplayBadge = `<span class="badge bg-primary bg-opacity-25 text-primary border px-3 py-2 rounded-pill ms-1">${course.type}</span>`;
      let durationDisplay = course.duration ? `<div class="d-flex align-items-center mt-1"><i class="bi bi-calendar-range me-2"></i><span class="small text-muted">${course.duration}</span></div>` : '';

      grid.innerHTML += `
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative">
            <div class="position-absolute top-0 start-0 w-100" style="background-color: #2b4d7e; height: 5px;"></div>
            <div class="card-body p-4 d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">${course.level}</span>
                  ${typeDisplayBadge}
                </div>
              </div>
              <h5 class="fw-bold mb-3 text-dark">${course.name}</h5>
              
              <div class="mb-3 px-3 py-2 bg-light rounded-3">
                <div class="d-flex align-items-center mb-1">
                  <i class="bi bi-calendar-check me-2"></i>
                  <span class="small fw-bold text-dark">วัน:</span>
                  <span class="small text-muted ms-2">${displayDays}</span>
                </div>
                <div class="d-flex align-items-center">
                  <i class="bi bi-clock me-2"></i>
                  <span class="small fw-bold text-dark">เวลา:</span>
                  <span class="small text-muted ms-2">${displayTime}</span>
                </div>
                ${durationDisplay}
              </div>
              
              <div class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; min-height: 40px;" title="${detailsText}">
                <i class="bi bi-info-circle me-1"></i> ${detailsText}
              </div>

              <div class="d-flex flex-column mb-2 rounded-3">
                <div class="d-flex justify-content-between align-items-center mb-0 p-2 rounded-3 mt-2" style="background-color: #e2e8f0;">
                  <span class="text-muted small fw-bold">ราคา</span>
                  <span class="fw-bold fs-5 text-primary">${Number(course.price).toLocaleString()} ฿</span>
                </div>
              </div>
      
              <div class="mb-3">
                <div>${otherExpenseHtml}</div>
              </div>
            
              <div class="d-flex justify-content-end gap-2 mt-auto">
                <button class="btn border-primary text-primary bg-white" onclick="goToStudents('${course.id}', '${course.name.replace(/'/g, "\\'")}')"><i class="bi bi-people-fill me-1"></i> นักเรียน</button>
                <button class="btn btn-s btn-outline-primary" title="แก้ไขข้อมูล" onclick="openCourseForm('${course.id}')"><i class="bi bi-pencil-square"></i></button>
                <button class="btn btn-s btn-outline-danger" title="ลบข้อมูล" onclick="promptDeleteCourse('${course.id}', '${course.name.replace(/'/g, "\\'")}')"><i class="bi bi-trash"></i></button>
              </div>
            </div>
          </div>
        </div>
      `;
    });
  }

  function openCourseForm(id = null) {
    document.getElementById('courseForm').reset();
    setupYearMonthOptions(); 
    
    document.querySelectorAll('.course-day-cb').forEach(cb => cb.checked = false);
    document.getElementById('courseTimeStart').value = '';
    document.getElementById('courseTimeEnd').value = '';
    
    if(startPicker) startPicker.clear();
    if(endPicker) endPicker.clear();

    courseModalInstance = new bootstrap.Modal(document.getElementById('courseModal'));
    
    if (id) {
      document.getElementById('courseModalTitle').innerText = 'แก้ไขคอร์สเรียน';
      const course = allCoursesList.find(c => c.id === id);
      document.getElementById('courseId').value = course.id;
      document.getElementById('courseName').value = course.name;
      document.getElementById('courseLevel').value = course.level;
      document.getElementById('coursePrice').value = course.price;
      document.getElementById('courseDetails').value = course.details || ''; 
      
      document.getElementById('courseOtherExpenseName').value = course.otherExpenseName || '';
      document.getElementById('courseOtherExpensePrice').value = course.otherExpensePrice || '';

      document.getElementById('courseType').value = course.type || 'คอร์สตัวต่อตัว';
      document.getElementById('courseMonth').value = course.month || '';
      document.getElementById('courseYearBE').value = course.yearBE || '';

      if (course.duration) {
        let cleanDuration = course.duration.toString();
        let durationParts = cleanDuration.split(' - ');
        if (durationParts.length === 2) {
          if(startPicker) startPicker.setDate(durationParts[0].trim(), true);
          if(endPicker) endPicker.setDate(durationParts[1].trim(), true);
        } else {
          if(startPicker) startPicker.setDate(cleanDuration.trim(), true);
        }
      }

      if (course.days) {
        let daysArr = course.days.split(',').map(d => d.trim());
        document.querySelectorAll('.course-day-cb').forEach(cb => {
          if (daysArr.includes(cb.value)) cb.checked = true;
        });
      }

      if (course.time && course.time.includes('-')) {
        let timeParts = course.time.split('-');
        document.getElementById('courseTimeStart').value = timeParts[0].trim();
        document.getElementById('courseTimeEnd').value = timeParts[1].trim();
      }
    } else {
      document.getElementById('courseModalTitle').innerText = 'เพิ่มคอร์สเรียนใหม่';
      document.getElementById('courseId').value = '';
      document.getElementById('courseType').value = 'คอร์สตัวต่อตัว';
      setDefaultMonthYear(); 
    }
    courseModalInstance.show();
  }

  function saveCourse() {
    const name = document.getElementById('courseName').value;
    const level = document.getElementById('courseLevel').value;
    const price = document.getElementById('coursePrice').value;
    const details = document.getElementById('courseDetails').value; 
    const otherExpenseName = document.getElementById('courseOtherExpenseName').value;
    const otherExpensePrice = document.getElementById('courseOtherExpensePrice').value;
    const courseType = document.getElementById('courseType').value;
    const courseMonth = document.getElementById('courseMonth').value;
    const courseYearBE = document.getElementById('courseYearBE').value;
    const rawStartDate = document.getElementById('courseStartDate').value;
    const rawEndDate = document.getElementById('courseEndDate').value;

    if(!name || !level || !price) {
      new bootstrap.Modal(document.getElementById('validationModal')).show();
      return;
    }

    let selectedDays = [];
    document.querySelectorAll('.course-day-cb:checked').forEach(cb => selectedDays.push(cb.value));
    let daysStr = selectedDays.join(', ');

    let timeStart = document.getElementById('courseTimeStart').value;
    let timeEnd = document.getElementById('courseTimeEnd').value;
    let timeStr = (timeStart && timeEnd) ? `${timeStart} - ${timeEnd}` : '';
    let durationStr = (rawStartDate && rawEndDate) ? `${rawStartDate} - ${rawEndDate}` : (rawStartDate || rawEndDate || '');

    const data = {
      id: document.getElementById('courseId').value,
      name: name, level: level, price: price, details: details,
      otherExpenseName: otherExpenseName, otherExpensePrice: otherExpensePrice,
      days: daysStr, time: timeStr, type: courseType, month: courseMonth,
      yearBE: courseYearBE, duration: durationStr
    };

    showLoading('กำลังบันทึกคอร์สเรียน...');
    courseModalInstance.hide();

    callAPI('saveCourse', data, 'POST').then(function(res) {
      hideLoading();
      if(res.success) {
        loadCoursesData();
      } else {
        alert('เกิดข้อผิดพลาด: ' + res.message);
      }
    });
  }

  function promptDeleteCourse(id, name) {
    document.getElementById('delCourseTargetId').value = id;
    document.getElementById('delCourseLabelName').innerText = name;
    deleteCourseModalInstance = new bootstrap.Modal(document.getElementById('deleteCourseConfirmModal'));
    deleteCourseModalInstance.show();
  }

  function confirmDeleteCourse() {
    const id = document.getElementById('delCourseTargetId').value;
    deleteCourseModalInstance.hide();
    showLoading('กำลังลบคอร์ส...');
    
    callAPI('deleteCourse', { courseId: id }, 'POST').then(function(res) {
      hideLoading();
      if(res.success) loadCoursesData();
      else alert('ลบไม่สำเร็จ: ' + res.message);
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