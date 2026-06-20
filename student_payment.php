<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์ (ยอมรับทั้ง 'student' และ 'นักเรียน')
$role = $_SESSION['sessionRole'] ?? '';
if ($role !== 'student' && $role !== 'นักเรียน') {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['userRowId'] ?? ''; // รหัส ST-XXXX

// ==========================================
// API: ดึงรายการค้างชำระและรอตรวจสอบ (Backend)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'getApprovedCoursesForPayment') {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);

    try {
        // ดึงข้อมูลการลงทะเบียนที่ได้รับการอนุมัติแล้วทั้งหมดของนักเรียน 
        // เพิ่มเงื่อนไข e.deleted_at IS NULL เพื่อไม่ดึงข้อมูลที่ถูก Soft Delete
        $sql = "SELECT e.*, c.name, c.level, c.price, c.other_expense_name, c.other_expense_price, c.course_type, c.course_month, c.duration 
                FROM enrollments e 
                JOIN courses c ON e.course_id = c.course_id 
                WHERE e.user_id = ? 
                  AND e.approval_status IN ('approved', 'อนุมัติแล้ว') 
                  AND e.deleted_at IS NULL
                  AND c.deleted_at IS NULL";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->get_result();
        
        $pendingPayments = [];

        // วนลูปเช็คทุกรายการลงทะเบียน
        while ($row = $result->fetch_assoc()) {
            $pStatus = $row['payment_status'];
            
            // กรองเฉพาะรายการที่ยังไม่ได้ชำระ หรือ รอตรวจสอบสลิป
            // (เอา approval_payment ออกแล้ว เพื่อให้ card หายไปเมื่อแอดมินอนุมัติสลิป)
            if (empty($pStatus) || $pStatus === 'pending_payment' || $pStatus === 'รอชำระเงิน' || $pStatus === 'รอตรวจสอบ') {
                
                // เช็คว่ามีการแนบสลิปหรือระบุว่าจ่ายเงินสดมาแล้วหรือยัง
                $hasPayment = (!empty($row['slip_url']) || $row['payment_method'] === 'เงินสด') ? true : false;
                
                // จัดการข้อความแสดงเดือน (อิงจากรอบเดือนที่จ่าย หรือ เดือนของคอร์ส หรือเป็นแบบรายครั้ง)
                $monthYearText = !empty($row['paid_month']) ? $row['paid_month'] : (!empty($row['course_month']) ? $row['course_month'] : 'รายครั้ง');

                $pendingPayments[] = [
                    'enrollId' => $row['enroll_id'],
                    'courseId' => $row['course_id'],
                    'courseName' => $row['name'],
                    'level' => $row['level'],
                    'price' => $row['price'],
                    'otherExpenseName' => $row['other_expense_name'] ?? '',
                    'otherExpensePrice' => (float)($row['other_expense_price'] ?? 0),
                    'paymentStatus' => 'pending_payment', 
                    'hasPayment' => $hasPayment,          
                    'monthYearText' => $monthYearText,
                    'isNewRow' => false
                ];
            }
        }

        echo json_encode($pendingPayments);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - รายการชำระเงิน</title>
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
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" onclick="toggleSidebar()"></div>

  <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;" id="studentSidebar">
    <div class="sidebar-header border-bottom border-secondary pt-4 pb-3 position-relative">
      <button class="btn text-white position-absolute top-0 end-0 m-2 d-lg-none" style="background: transparent; border: none; font-size: 1.5rem;" onclick="toggleSidebar()"><i class="bi bi-x-lg"></i></button>
      <div class="d-flex align-items-center justify-content-center mb-2">
        <img src="img/logo.png" onerror="this.src='https://via.placeholder.com/50'" style="width: 50px; height: 50px; object-fit: contain;"> 
      </div>
      <h5 class="fw-bold mb-0 text-center">Sci Math Academy</h5>
      <div class="text-center"><small style="color: #cbd5e1;">สถาบันสอนพิเศษ สว่างแดนดิน</small></div>
    </div>
    <div class="nav-menu mt-3 flex-grow-1 px-3">
      <a href="main.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-house-door me-2"></i> หน้าหลัก</a>
      <a href="student_courses.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-collection me-2"></i> คอร์สทั้งหมด</a>
      <a href="student_payment.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;"><i class="bi bi-wallet2 text-primary me-2"></i> แจ้งชำระเงิน</a>
    </div>
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5" style="background-color: #f4f6f9;">
    <div class="d-lg-none mb-3">
      <button class="btn btn-light shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div id="paymentGridSection">
      <div class="mb-4">
        <h4 class="fw-bold text-dark"><i class="bi bi-wallet2 text-primary me-2"></i>รายการค้างชำระ / รอตรวจสอบ</h4>
      </div>
      <div class="row g-4" id="paymentCourseGrid"></div>
    </div>
  </main>
</div>

<script>
  function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  document.addEventListener("DOMContentLoaded", function() {
    const grid = document.getElementById('paymentCourseGrid');
    grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังตรวจสอบรายการ...</div>';
    
    fetch('student_payment.php?action=getApprovedCoursesForPayment')
      .then(res => res.json())
      .then(courses => {
        grid.innerHTML = '';
        if (!courses || courses.length === 0) {
          grid.innerHTML = '<div class="col-12 text-center py-5"><i class="bi bi-check-circle text-success mb-3" style="font-size: 3rem;"></i><h5 class="text-muted fw-bold">ยอดเยี่ยม! คุณไม่มีรายการค้างชำระ</h5></div>';
          return;
        }

        courses.forEach(course => {
          let badgeHtml = '', btnHtml = '', topBorderClass = '';
          let safeName = encodeURIComponent(course.courseName);
          let safeMonth = encodeURIComponent(course.monthYearText);
          let safeExpName = encodeURIComponent(course.otherExpenseName);

          if (course.hasPayment) {
            topBorderClass = 'bg-warning';
            badgeHtml = `<span class="badge bg-warning-subtle px-3 py-2 rounded-pill" style="color: rgb(255, 133, 7) !important;"><i class="bi bi-hourglass-split me-1"></i>รอตรวจสอบ</span>`;
            btnHtml = `<button class="btn btn-warning text-dark w-100 fw-bold shadow-sm py-2" style="border-radius: 8px;" onclick="window.location.href='student_payment_pending.php?enrollId=${course.enrollId}'"><i class="bi bi-search me-1"></i> ดูรายละเอียด</button>`;
          } else {
            let isMonthly = course.monthYearText && course.monthYearText !== 'รายครั้ง';
            let badgeText = isMonthly ? `บิลใหม่รอบเดือน (${course.monthYearText})` : 'รอชำระเงิน';
            topBorderClass = course.isNewRow ? 'bg-danger' : 'bg-primary';
            badgeHtml = `<span class="badge ${course.isNewRow ? 'bg-danger' : 'bg-primary'} text-white px-3 py-2 rounded-pill">${badgeText}</span>`;
            btnHtml = `<button class="btn btn-primary w-100 fw-bold shadow-sm py-2" style="background-color: #2b4d7e; border-radius: 8px;" onclick="window.location.href='student_submit_payment.php?enrollId=${course.enrollId}&courseId=${course.courseId}&courseName=${safeName}&level=${course.level}&price=${course.price}&otherExpenseName=${safeExpName}&otherExpensePrice=${course.otherExpensePrice}&monthYearText=${safeMonth}&isNewRow=${course.isNewRow}'"><i class="bi bi-wallet2 me-1"></i> แจ้งชำระเงิน</button>`;
          }

          grid.innerHTML += `
            <div class="col-md-6 col-lg-4">
              <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative">
                <div class="position-absolute top-0 start-0 w-100 ${topBorderClass}" style="height: 5px;"></div>
                <div class="card-body p-4 d-flex flex-column">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">${course.level}</span>
                    ${badgeHtml}
                  </div>
                  <h5 class="fw-bold mb-3 text-dark">${course.courseName}</h5>
                  <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded-3" style="background-color: #f8faff;">
                    <span class="text-muted small fw-bold">ยอดเริ่มต้นที่ต้องชำระ</span>
                    <span class="fw-bold fs-5 text-primary">${Number(course.price).toLocaleString()} ฿</span>
                  </div>
                  <div class="mt-auto">${btnHtml}</div>
                </div>
              </div>
            </div>`;
        });
      });
  });
</script>
</body>
</html>