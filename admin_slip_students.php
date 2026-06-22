<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// Helper function สำหรับส่ง JSON Response เพื่อลดโค้ดซ้ำซ้อน
function sendJSON($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

$action = $_GET['action'] ?? '';

// ==========================================
// API 1: สร้างบิลรอบเดือนใหม่ให้นักเรียนทั้งคอร์ส
// ==========================================
if ($action === 'startNewMonth') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cId = $input['courseId'] ?? '';
    $newMonth = $input['newMonth'] ?? ''; // รูปแบบข้อมูลที่ส่งมาจะเป็น "มค 2569"

    if (!$cId || !$newMonth) sendJSON(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);

    try {
        $conn->begin_transaction();

        // ดึง user_id ของนักเรียนทุกคนที่ผ่านการอนุมัติในคอร์สนี้
        $stmt = $conn->prepare("SELECT DISTINCT e.user_id FROM enrollments e JOIN users u ON e.user_id = u.user_id WHERE e.course_id = ? AND e.approval_status IN ('approved', 'อนุมัติแล้ว') AND e.deleted_at IS NULL AND u.deleted_at IS NULL");
        $stmt->bind_param("s", $cId);
        $stmt->execute();
        $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($users) === 0) throw new Exception("ไม่พบนักเรียนในคอร์สนี้ หรือนักเรียนถูกลบออกจากระบบหมดแล้ว");

        // เช็คว่าใครที่เคยถูกสร้างบิลรอบเดือนนี้ไปแล้วบ้าง
        $stmtCheck = $conn->prepare("SELECT e.user_id FROM enrollments e JOIN users u ON e.user_id = u.user_id WHERE e.course_id = ? AND e.paid_month = ? AND e.deleted_at IS NULL AND u.deleted_at IS NULL");
        $stmtCheck->bind_param("ss", $cId, $newMonth);
        $stmtCheck->execute();
        $existingUsers = array_column($stmtCheck->get_result()->fetch_all(MYSQLI_ASSOC), 'user_id');

        // เตรียมคำสั่ง Insert บิลใหม่
        $stmtInsert = $conn->prepare("INSERT INTO enrollments (enroll_id, user_id, course_id, approval_status, payment_status, paid_month) VALUES (?, ?, ?, 'approved', 'pending_payment', ?)");

        $insertedCount = 0;
        foreach ($users as $u) {
            if (!in_array($u['user_id'], $existingUsers)) {
                $newEnrollId = uniqid('EN'); 
                $stmtInsert->bind_param("ssss", $newEnrollId, $u['user_id'], $cId, $newMonth);
                $stmtInsert->execute();
                $insertedCount++;
            }
        }

        $conn->commit();
        sendJSON(['success' => true, 'message' => "เพิ่มรอบเดือนใหม่ ($newMonth) ให้นักเรียนจำนวน $insertedCount คนเรียบร้อยแล้ว"]);
    } catch (Exception $e) {
        $conn->rollback();
        sendJSON(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ==========================================
// API 2: ดึงรายชื่อนักเรียนในคอร์สมาแสดงในตาราง 
// ==========================================
if ($action === 'getCourseStudentsForSlips') {
    $cId = $_GET['courseId'] ?? '';
    try {
        $sql = "SELECT e.*, u.full_name, u.nickname, c.price, c.course_month 
                FROM enrollments e 
                JOIN users u ON e.user_id = u.user_id 
                JOIN courses c ON e.course_id = c.course_id 
                WHERE e.course_id = ? AND e.approval_status IN ('approved', 'อนุมัติแล้ว') AND e.deleted_at IS NULL AND u.deleted_at IS NULL
                ORDER BY e.timestamp DESC";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $cId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $payDate = $payTime = '-';
            if (!empty($row['approved_date'])) {
                $dt = new DateTime($row['approved_date']);
                $payDate = $dt->format('d/m/') . ((int)$dt->format('Y') + 543);
                $payTime = $dt->format('H:i') . ' น.';
            }
            
            $data[] = [
                'enrollId' => $row['enroll_id'],
                'studentName' => $row['full_name'] . (!empty($row['nickname']) ? ' (' . $row['nickname'] . ')' : ''),
                'paidMonth' => $row['paid_month'], 
                'courseMonth' => $row['course_month'], 
                'paymentStatus' => $row['payment_status'],
                'paymentMethod' => $row['payment_method'],
                'slipUrl' => $row['slip_url'],
                'payDate' => $payDate,
                'payTime' => $payTime,
                'netPrice' => (float)$row['net_price'],
                'price' => (float)$row['price']
            ];
        }
        sendJSON($data);
    } catch (Exception $e) {
        sendJSON(['error' => $e->getMessage()]);
    }
}

// ==========================================
// API 3: อัปเดตสถานะสลิป (อนุมัติ / ปฏิเสธ) 
// ==========================================
if ($action === 'updateSlipPaymentStatus') {
    $input = json_decode(file_get_contents('php://input'), true);
    $enrollId = $input['enrollId'] ?? '';
    $newStatus = $input['newStatus'] ?? '';

    if (!$enrollId || !$newStatus) sendJSON(['success' => false, 'message' => 'ข้อมูลไม่ครบถ้วน']);

    try {
        if ($newStatus === 'pending_payment') {
            $stmt = $conn->prepare("UPDATE enrollments SET payment_status = ?, slip_url = NULL, approved_date = NULL, net_price = 0, paid_month = NULL, payment_method = NULL, include_other_expense = 0 WHERE enroll_id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE enrollments SET payment_status = ? WHERE enroll_id = ?");
        }
        $stmt->bind_param("ss", $newStatus, $enrollId);
        $stmt->execute();
        sendJSON(['success' => true]);
    } catch (Exception $e) {
        sendJSON(['success' => false, 'message' => $e->getMessage()]);
    }
}

// ==========================================
// ส่วนหน้าจอปกติ (UI)
// ==========================================
if (empty($_GET['courseId'])) {
    header('Location: admin_slips.php');
    exit;
}

$courseId = $_GET['courseId'];
$stmt = $conn->prepare("SELECT name, duration, course_type, course_month FROM courses WHERE course_id = ? AND deleted_at IS NULL");
$stmt->bind_param("s", $courseId);
$stmt->execute();
$courseResult = $stmt->get_result()->fetch_assoc();

if (!$courseResult) {
    echo "<script>alert('ไม่พบคอร์สเรียนนี้ หรือคอร์สถูกลบออกจากระบบแล้ว'); window.location.href='admin_slips.php';</script>";
    exit;
}

$courseName = $courseResult['name'];
$courseType = $courseResult['course_type'];
$duration = $courseResult['duration'] ?? '';

// --- จุดที่แก้ไข: เปลี่ยนมาดึงข้อมูลรอบเดือนที่มีอยู่จริงจากตาราง enrollments คอลัมน์ paid_month ---
$stmtMonths = $conn->prepare("SELECT DISTINCT paid_month FROM enrollments WHERE course_id = ? AND paid_month IS NOT NULL AND paid_month != '' AND deleted_at IS NULL ORDER BY paid_month DESC");
$stmtMonths->bind_param("s", $courseId);
$stmtMonths->execute();
$monthsResult = $stmtMonths->get_result()->fetch_all(MYSQLI_ASSOC);
$monthsArray = array_column($monthsResult, 'paid_month');
// -----------------------------------------------------------------------------------------

// จัดการโครงสร้างค่าเดือนและปีปัจจุบันให้อยู่ในรูปแบบ พ.ศ. (เช่น "มิย 2569")
$thaiMonths = ["01" => "มค", "02" => "กพ", "03" => "มีค", "04" => "เมย", "05" => "พค", "06" => "มิย", "07" => "กค", "08" => "สค", "09" => "กย", "10" => "ตค", "11" => "พย", "12" => "ธค"];
$currentYearTh = (int)date("Y") + 543;
$currentMonthTh = $thaiMonths[date("m")] . " " . $currentYearTh;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - ตรวจสอบการชำระเงิน</title>
  
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
    @media (max-width: 991.98px) { .sidebar { position: fixed; left: -260px; height: 100vh; } .sidebar.show { left: 0; } .mobile-overlay.show { display: block; } }
    @media (min-width: 992px) { .btn-toggle-menu { display: none !important; } }
    .full-page-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(255,255,255,0.8); z-index: 1060; display: flex; flex-direction: column; justify-content: center; align-items: center; visibility: hidden; opacity: 0; transition: opacity 0.3s; }
    .full-page-overlay.show { visibility: visible; opacity: 1; }
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

  <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;" id="adminSidebar">
    <div class="sidebar-header border-bottom border-secondary pt-4 pb-3 position-relative">
      <button class="btn text-white position-absolute top-0 end-0 m-2 d-lg-none" style="background: transparent; border: none; font-size: 1.5rem;" onclick="toggleSidebar()"><i class="bi bi-x-lg"></i></button>
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

    <div id="adminSlipsStudentsView">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center">
          <button class="btn me-2" onclick="window.location.href='admin_slips.php'"><i class="bi bi-arrow-left fs-5"></i></button>
          <h4 class="fw-bold mb-0 text-dark">นักเรียนคอร์ส: <span class="text-primary"><?= htmlspecialchars($courseName); ?></span></h4>
        </div>

        <div class="d-flex align-items-center gap-2">
           <?php if ($courseType === 'คอร์สกลุ่ม'): ?>
          <select id="monthDropdown" class="form-select shadow-sm fw-bold" style="min-width: 130px; border-radius: 8px;" onchange="loadStudentData()">
            <?php
            if (empty($monthsArray)) {
                echo '<option value="">ไม่มีข้อมูลเดือน</option>';
            } else {
                $hasCurrentMonth = in_array($currentMonthTh, $monthsArray);
                foreach ($monthsArray as $index => $m) {
                    $selected = ($hasCurrentMonth && $m === $currentMonthTh) || (!$hasCurrentMonth && $index === 0) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($m) . '" ' . $selected . '>รอบเดือน ' . htmlspecialchars($m) . '</option>';
                }
            }
            ?>
          </select>

          <button class="btn btn-primary fw-bold px-4 py-2 shadow-sm rounded-pill text-nowrap" onclick="openNewMonthModal()">
            <i class="bi bi-calendar-plus me-2"></i> เริ่มรอบเดือนใหม่
          </button>
          <?php endif; ?>
        </div>
      </div>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr class="text-nowrap">
                <th class="py-3 px-4 fw-bold border-0">ชื่อ-สกุล (ชื่อเล่น)</th>
                <th class="py-3 fw-bold border-0 text-center">รอบเดือน</th>
                <th class="py-3 fw-bold border-0 text-center">สถานะการชำระเงิน</th>
                <th class="py-3 px-4 fw-bold border-0 text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody id="adminSlipsStudentsTableBody" style="border-top: none;">
              <tr><td colspan="4" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลนักเรียน...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div id="adminSlipsDetailView" style="display: none;">
      <div class="d-flex align-items-center mb-4">
        <button class="btn me-2" onclick="backToSlipStudentsView()"><i class="bi bi-arrow-left fs-5"></i></button>
        <h4 class="fw-bold mb-0 text-dark">ตรวจสอบการชำระเงิน</h4>
      </div>

      <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
          <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100 bg-primary" style="height: 5px;"></div>
            <div class="card-body p-4 p-md-5 text-center">
              <h5 class="fw-bold text-dark mb-1" id="slipDetailStudentName"></h5>
              <p class="text-muted small mb-4" id="slipDetailCourseName">คอร์ส: <?= htmlspecialchars($courseName); ?></p>

              <div class="p-3 bg-light rounded-3 mb-4 text-start">
                <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                  <span class="text-muted fw-bold small">ช่องทางการชำระ:</span>
                  <span class="badge bg-success" id="slipDetailMethod" style="font-size: 0.85rem;"></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted fw-bold small">วันที่โอน/ชำระ:</span>
                  <span class="fw-bold text-dark" id="slipDetailDate"></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                  <span class="text-muted fw-bold small">เวลา:</span>
                  <span class="fw-bold text-dark" id="slipDetailTime"></span>
                </div>
                <hr class="text-muted">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-muted fw-bold small">ยอดเงินสุทธิ:</span>
                  <span class="fw-bold fs-4 text-primary" id="slipDetailAmount"></span>
                </div>
              </div>

              <div class="mb-4">
                <p class="fw-bold text-dark mb-2">หลักฐานการชำระเงิน</p>
                <div id="slipImageContainer" class="border rounded-3 p-2 bg-light d-flex flex-column justify-content-center align-items-center" style="min-height: 200px;">
                </div>
              </div>

              <input type="hidden" id="currentSlipEnrollId">
              
              <div class="d-flex gap-2 justify-content-center mt-4" id="slipActionButtonsContainer">
                <button class="btn btn-danger fw-bold px-4 py-2" style="border-radius: 8px;" onclick="promptUpdateSlipStatus('pending_payment')">
                  <i class="bi bi-x-circle me-1"></i> ปฏิเสธ (ให้ชำระใหม่)
                </button>
                <button class="btn btn-success fw-bold px-4 py-2" style="border-radius: 8px;" onclick="promptUpdateSlipStatus('approval_payment')">
                  <i class="bi bi-check-circle me-1"></i> อนุมัติการชำระเงิน
                </button>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="newMonthModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold text-primary"><i class="bi bi-calendar2-plus me-2"></i>เริ่มต้นรอบเดือนใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 text-center">
        <p class="text-muted small mb-4">ระบบจะสร้างบิลเรียกเก็บเงินใหม่ ให้นักเรียนทุกคนในคอร์สนี้โดยอัตโนมัติ</p>
        <div class="text-start">
          <label class="form-label fw-bold">เลือกเดือนที่ต้องการเริ่มเรียน</label>
          <select id="selectNewMonth" class="form-select mb-2 fw-bold text-dark" style="border-radius: 8px;">
  <?php 
    $thMonthsFull = [1=>"มกราคม", 2=>"กุมภาพันธ์", 3=>"มีนาคม", 4=>"เมษายน", 5=>"พฤษภาคม", 6=>"มิถุนายน", 7=>"กรกฎาคม", 8=>"สิงหาคม", 9=>"กันยายน", 10=>"ตุลาคม", 11=>"พฤศจิกายน", 12=>"ธันวาคม"];
    $thMonthsShort = [1=>"มค", 2=>"กพ", 3=>"มีค", 4=>"เมย", 5=>"พค", 6=>"มิย", 7=>"กค", 8=>"สค", 9=>"กย", 10=>"ตค", 11=>"พย", 12=>"ธค"];
    
    $currentM = (int)date('n'); // เดือนปัจจุบัน (1-12)
    $currentY = (int)date('Y'); // ปี ค.ศ. ปัจจุบัน

    // วนลูปสร้างตัวเลือก 12 เดือน: ย้อนหลัง 1 เดือน และล่วงหน้า 10 เดือน
    for($i = -1; $i <= 10; $i++) {
        $calcMonth = $currentM + $i;
        $calcYear = $currentY;
        
        // ปรับการคำนวณกรณีข้ามปี
        if ($calcMonth < 1) {
            $calcMonth += 12;
            $calcYear--; // ปีย้อนหลัง
        } elseif ($calcMonth > 12) {
            $calcMonth -= 12;
            $calcYear++; // ปีล่วงหน้า
        }
        
        $yearTh = $calcYear + 543;
        $val = $thMonthsShort[$calcMonth] . " " . $yearTh;
        
        // ให้ค่า Default เลือกที่เดือนปัจจุบันพอดี
        $selected = ($i === 0) ? 'selected' : ''; 
        
        echo "<option value='{$val}' {$selected}>รอบเดือน {$thMonthsFull[$calcMonth]} ({$val})</option>";
    }
  ?>
</select>
        </div>
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="btn btn-primary px-4 rounded-pill fw-bold" onclick="confirmStartNewMonth()">ยืนยันการสร้าง</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="slipAlertModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body text-center p-5">
        <i id="slipAlertIcon" class="bi bi-check-circle-fill text-success mb-4 d-block" style="font-size: 4.5rem;"></i>
        <h4 class="fw-bold mb-2 text-dark" id="slipAlertTitle">ดำเนินการสำเร็จ</h4>
        <p class="text-muted mb-4" id="slipAlertMessage"></p>
        <button type="button" class="btn btn-primary px-5 rounded-pill fw-bold" data-bs-dismiss="modal">ตกลง</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="slipConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body text-center p-4 pb-2">
        <i class="bi bi-question-circle text-primary mb-3 d-block" style="font-size: 3rem;"></i>
        <h5 class="fw-bold mb-2">ยืนยันการดำเนินการ</h5>
        <p class="text-muted mb-4" id="slipConfirmMessage"></p>
        <input type="hidden" id="tempSlipStatusToUpdate">
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="btn btn-primary px-4 rounded-pill" id="btnConfirmSlipStatus" onclick="executeSlipStatusUpdate()">ยืนยัน</button>
      </div>
    </div>
  </div>
</div>

<div id="slipFullPageLoading" class="full-page-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(255,255,255,0.8); z-index: 9999; flex-direction: column; justify-content: center; align-items: center;">
  <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status"></div>
  <h4 class="mt-3 fw-bold text-primary" id="slipLoadingText">กำลังประมวลผล...</h4>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'admin_slip_students.php';
  let currentSlipCourseId = '<?= htmlspecialchars($courseId, ENT_QUOTES, 'UTF-8') ?>';
  let currentSlipCourseName = '<?= htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8') ?>';
  let currentSlipStudentsData = [];

  // ประกาศตัวแปรเก็บ Instance ของ Bootstrap Modal ป้องกันปัญหา Backdrop ทับซ้อน และแก้อาการปุ่มกดไม่ติด
  let bootstrapNewMonthModal = null;
  let bootstrapSlipAlertModal = null;
  let bootstrapSlipConfirmModal = null;

  document.addEventListener("DOMContentLoaded", function() {
    // ผูก Instance Modal ครั้งเดียวหลังจากที่ DOM โหลดเสร็จสิ้น เพื่อเสถียรภาพสูงสุดของ UI 
    bootstrapNewMonthModal = new bootstrap.Modal(document.getElementById('newMonthModal'));
    bootstrapSlipAlertModal = new bootstrap.Modal(document.getElementById('slipAlertModal'));
    bootstrapSlipConfirmModal = new bootstrap.Modal(document.getElementById('slipConfirmModal'));
    
    loadStudentData();
  });

  async function callAPI(action, data = null, method = 'POST') {
    try {
      let url = `${API_URL}?action=${action}`;
      let options = { method: method };

      if (method === 'POST') {
        options.headers = { 'Content-Type': 'application/json' };
        options.body = JSON.stringify(data);
      } else if (method === 'GET' && data) {
        url += `&${new URLSearchParams(data).toString()}`;
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

  function showSlipLoading(text) {
    document.getElementById('slipLoadingText').innerText = text;
    document.getElementById('slipFullPageLoading').style.display = 'flex';
  }

  function hideSlipLoading() {
    document.getElementById('slipFullPageLoading').style.display = 'none';
  }

  function showSlipAlert(message, type = 'success') {
    const icon = document.getElementById('slipAlertIcon');
    const title = document.getElementById('slipAlertTitle');

    icon.className = type === 'success' ? 'bi bi-check-circle-fill text-success mb-4 d-block' : 'bi bi-x-circle-fill text-danger mb-4 d-block';
    title.innerText = type === 'success' ? 'ดำเนินการสำเร็จ' : 'เกิดข้อผิดพลาด';

    document.getElementById('slipAlertMessage').innerText = message;
    if (bootstrapSlipAlertModal) bootstrapSlipAlertModal.show();
  }

  function openNewMonthModal() {
    // ไม่มีการซ่อนเดือนแล้ว เปิด Modal ขึ้นมาตรงๆ ได้เลย
    if (bootstrapNewMonthModal) bootstrapNewMonthModal.show();
  }

  function confirmStartNewMonth() {
    const selectedMonth = document.getElementById('selectNewMonth').value;
    if (bootstrapNewMonthModal) bootstrapNewMonthModal.hide();

    showSlipLoading('กำลังสร้างรายการรอบเดือนใหม่...');

    fetch('admin_slip_students.php?action=startNewMonth', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        courseId: currentSlipCourseId,
        newMonth: selectedMonth
      })
    })
    .then(res => res.json())
    .then(data => {
      hideSlipLoading();
      if (data.success) {
        showSlipAlert(data.message, 'success');
        setTimeout(() => { window.location.reload(); }, 1500); 
      } else {
        showSlipAlert(data.message, 'error');
      }
    })
    .catch(err => {
      hideSlipLoading();
      showSlipAlert('เกิดข้อผิดพลาดในการเชื่อมต่อ', 'error');
    });
  }

  function loadStudentData() {
    const monthDropdown = document.getElementById('monthDropdown');
    let selectedMonth = monthDropdown ? monthDropdown.value : ''; 
    const tbody = document.getElementById('adminSlipsStudentsTableBody');

    if (monthDropdown && !selectedMonth) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">กรุณาสร้างรอบเดือนใหม่เพื่อดูข้อมูล</td></tr>';
        return;
    }

    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลนักเรียน...</td></tr>';

    callAPI('getCourseStudentsForSlips', {courseId: currentSlipCourseId, month: selectedMonth}, 'GET').then(function(students) {
      if(students && !students.message && !students.error) {
        let filteredStudents = monthDropdown ? students.filter(s => s.paidMonth === selectedMonth) : students;
        currentSlipStudentsData = filteredStudents;
        displaySlipStudentsList(filteredStudents); 
      } else {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-5 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูลนักเรียน</td></tr>`;
      }
    });
  }

  function displaySlipStudentsList(students) {
    const tbody = document.getElementById('adminSlipsStudentsTableBody');
    tbody.innerHTML = '';

    if (!students || students.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">ไม่พบรายการนักเรียนในรอบเดือนนี้</td></tr>';
      return;
    }

    students.forEach(student => {
      let statusBadge = '', actionBtn = '';
      let hasPaymentMethod = (student.slipUrl && student.slipUrl.trim() !== '') || student.paymentMethod === 'เงินสด';
      
      let monthText = student.paidMonth || 'ไม่ระบุ';

      if (student.paymentStatus === 'paid' || student.paymentStatus === 'approval_payment') {
        statusBadge = `<span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ชำระแล้ว</span>`;
        actionBtn = `<button class="btn btn-sm btn-outline-success fw-bold px-3" onclick="viewSlipDetail('${student.enrollId}')">รายละเอียด</button>`;
      } else if (student.paymentStatus === 'pending_payment') {
        if (hasPaymentMethod) {
          let btnText = student.paymentMethod === 'เงินสด' ? 'ตรวจสอบ (เงินสด)' : 'ตรวจสอบสลิป';
          let btnClass = student.paymentMethod === 'เงินสด' ? 'btn-success' : 'btn-primary';
          
          statusBadge = `<span class="badge px-3 py-2 rounded-pill" style="color: rgb(255, 133, 7) !important; background-color: rgba(255, 133, 7, 0.1) !important;"><i class="bi bi-hourglass-split me-1"></i>รอตรวจสอบ</span>`;
          actionBtn = `<button class="btn btn-sm ${btnClass} fw-bold px-3 shadow-sm" onclick="viewSlipDetail('${student.enrollId}')">${btnText}</button>`;
        } else {
          statusBadge = `<span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill"><i class="bi bi-hourglass-split me-1"></i>รอชำระเงิน</span>`;
          actionBtn = `<button class="btn btn-sm btn-light fw-bold text-muted" disabled>ยังไม่ชำระ</button>`;
        }
      } else {
        statusBadge = `<span class="badge bg-secondary-subtle text-secondary px-3 py-2 rounded-pill">${student.paymentStatus}</span>`;
        actionBtn = `<button class="btn btn-sm btn-outline-primary fw-bold px-3" onclick="viewSlipDetail('${student.enrollId}')">ดูข้อมูล</button>`;
      }

      tbody.innerHTML += `
        <tr>
          <td class="px-4 py-3 fw-medium text-dark text-nowrap"><i class="bi bi-person-circle text-secondary me-2"></i> ${student.studentName}</td>
          <td class="py-3 text-center text-nowrap">${monthText}</td>
          <td class="py-3 text-center text-nowrap">${statusBadge}</td>
          <td class="px-4 py-3 text-center text-nowrap">${actionBtn}</td>
        </tr>`;
    });
  }

  function viewSlipDetail(enrollId) {
    const student = currentSlipStudentsData.find(s => s.enrollId === enrollId);
    if(!student) return;

    document.getElementById('currentSlipEnrollId').value = enrollId;
    document.getElementById('slipDetailStudentName').innerText = student.studentName;
    document.getElementById('slipDetailMethod').innerText = student.paymentMethod || 'โอนเงิน';
    document.getElementById('slipDetailDate').innerText = student.payDate || '-';
    document.getElementById('slipDetailTime').innerText = student.payTime || '-';
    
    let finalAmount = student.netPrice || student.net_price || student.amount || student.price || 0;
    document.getElementById('slipDetailAmount').innerText = Number(finalAmount).toLocaleString() + ' ฿';

    const buttonsContainer = document.getElementById('slipActionButtonsContainer');
    buttonsContainer.style.setProperty('display', ['paid', 'approval_payment'].includes(student.paymentStatus) ? 'none' : 'flex', 'important'); 

    const imgContainer = document.getElementById('slipImageContainer');
    if (student.paymentMethod === 'เงินสด' && !student.slipUrl) {
      imgContainer.innerHTML = `
        <div class="text-center py-5">
          <i class="bi bi-cash-coin text-success mb-3 d-block" style="font-size: 4rem;"></i>
          <h5 class="fw-bold text-success mb-1">ชำระด้วยเงินสด</h5>
          <p class="text-muted small">รายการนี้ไม่มีการแนบสลิปผ่านออนไลน์</p>
        </div>`;
    } else if (student.slipUrl) {
      let url = student.slipUrl;
      if(url.includes("drive.google.com/file/d/")) {
        let fileId = url.match(/[-\w]{25,}/);
        if(fileId) {
          imgContainer.innerHTML = `
            <div class="w-100 rounded-3 overflow-hidden" style="border: 1px solid #dee2e6;">
              <iframe src="https://drive.google.com/file/d/${fileId[0]}/preview" style="width: 100%; height: 400px; border: none;"></iframe>
            </div>
            <div class="mt-3">
              <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-up-right me-1"></i> เปิดดูรูปภาพแบบเต็มจอ
              </a>
            </div>`;
        } else {
          imgContainer.innerHTML = `<a href="${url}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i> คลิกเพื่อเปิดดูรูปภาพ</a>`;
        }
      } else {
        imgContainer.innerHTML = `<a href="${url}" target="_blank"><img src="${url}" alt="Slip" style="max-width: 100%; max-height: 400px; object-fit: contain;"></a>`;
      }
    } else {
      imgContainer.innerHTML = `<span class="text-muted">ไม่พบข้อมูลการชำระเงิน</span>`;
    }

    document.getElementById('adminSlipsStudentsView').style.display = 'none';
    document.getElementById('adminSlipsDetailView').style.display = 'block';
    window.scrollTo(0, 0);
  }

  function promptUpdateSlipStatus(newStatus) {
    document.getElementById('tempSlipStatusToUpdate').value = newStatus;
    
    let confirmMsg = document.getElementById('slipConfirmMessage');
    let confirmBtn = document.getElementById('btnConfirmSlipStatus');
    let isApprove = ['approval_payment', 'paid'].includes(newStatus);
    
    confirmMsg.innerHTML = isApprove ? 'ยืนยันว่านักเรียนชำระเงินครบถ้วน ถูกต้องใช่หรือไม่?' : 'ต้องการปฏิเสธ และให้นักเรียนแจ้งชำระเงินใหม่ใช่หรือไม่? <br><small class="text-danger">(ข้อมูลสลิปเดิมจะถูกล้าง)</small>';
    confirmBtn.className = isApprove ? 'btn btn-success px-4 rounded-pill fw-bold' : 'btn btn-danger px-4 rounded-pill fw-bold';
    confirmBtn.innerText = isApprove ? 'ยืนยันอนุมัติ' : 'ยืนยันปฏิเสธ';
    
    if (bootstrapSlipConfirmModal) bootstrapSlipConfirmModal.show();
  }

  function executeSlipStatusUpdate() {
    if (bootstrapSlipConfirmModal) bootstrapSlipConfirmModal.hide();

    const enrollId = document.getElementById('currentSlipEnrollId').value;
    const newStatus = document.getElementById('tempSlipStatusToUpdate').value;
    
    showSlipLoading('กำลังอัปเดตสถานะ...');
    
    callAPI('updateSlipPaymentStatus', { enrollId: enrollId, newStatus: newStatus }, 'POST').then(function(res) {
      hideSlipLoading();
      if(res.success) {
        loadStudentData();
        document.getElementById('adminSlipsDetailView').style.display = 'none';
        document.getElementById('adminSlipsStudentsView').style.display = 'block';
        showSlipAlert('อัปเดตสถานะการชำระเงินเรียบร้อยแล้ว', 'success');
      } else {
        showSlipAlert(res.message, 'error');
      }
    });
  }

  function backToSlipStudentsView() {
    document.getElementById('adminSlipsDetailView').style.display = 'none';
    document.getElementById('adminSlipsStudentsView').style.display = 'block';
  }
</script>
</body>
</html>