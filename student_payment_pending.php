<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์
$role = $_SESSION['sessionRole'] ?? '';
if ($role !== 'student' && $role !== 'นักเรียน') {
    header('Location: index.php'); 
    exit;
}

// ตรวจสอบพารามิเตอร์
if (empty($_GET['enrollId'])) {
    header('Location: student_payment.php'); 
    exit;
}

$enrollId = $_GET['enrollId'];

// ดึงข้อมูลหลักฐานสลิปปัจจุบันแบบ Real-time ด้วย PHP SQL
$sql = "SELECT e.*, c.name, c.level, c.price, c.other_expense_name, c.other_expense_price 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.course_id 
        WHERE e.enroll_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$enrollId]);
$billing = $stmt->get_result()->fetch_assoc();

if (!$billing) {
    header('Location: student_payment.php'); 
    exit;
}

// จัดการแปลง Format รูปแบบวันเวลาให้สวยงามเป็น พ.ศ.
$payDateTimeStr = '-';
if (!empty($billing['approved_date'])) {
    $d = new DateTime($billing['approved_date']);
    $payDateTimeStr = $d->format('d/m/') . ($d->format('Y') + 543) . $d->format(' เวลา H:i น.');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - รายละเอียดการชำระเงิน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .app-layout { display: flex; min-height: 100vh; }
    .sidebar { width: 260px; flex-shrink: 0; z-index: 1000; }
    .nav-item { display: block; padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; text-decoration: none; transition: 0.2s; }
    .nav-item:hover { background-color: rgba(255,255,255,0.1); }
    .main-content { flex-grow: 1; padding: 20px; width: 100%; }
    .mobile-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 999; }
    @media (max-width: 991.98px) {
      .sidebar { position: fixed; left: -260px; height: 100vh; transition: all 0.3s; }
      .sidebar.show { left: 0; }
      .mobile-overlay.show { display: block; }
    }
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>
  <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;" id="studentSidebar">
    <div class="sidebar-header border-bottom border-secondary pt-4 pb-3 text-center position-relative">
      <button class="btn text-white position-absolute top-0 end-0 m-2 d-lg-none" style="background: transparent; border: none; font-size: 1.5rem;" onclick="toggleSidebar()"><i class="bi bi-x-lg"></i></button>
      <img src="img/logo.png" onerror="this.src='https://via.placeholder.com/50'" style="width: 50px; height: 50px; object-fit: contain;">
      <h5 class="fw-bold mb-0 mt-2">Sci Math Academy</h5>
      <div class="text-center"><small style="color: #cbd5e1;">สถาบันสอนพิเศษ สว่างแดนดิน</small></div>
    </div>
    <div class="nav-menu mt-3 flex-grow-1 px-3">
      <a href="main.php" class="nav-item text-white text-opacity-75"><i class="bi bi-house-door me-2"></i> หน้าหลัก</a>
      <a href="student_courses.php" class="nav-item text-white text-opacity-75"><i class="bi bi-collection me-2"></i> คอร์สทั้งหมด</a>
      <a href="student_payment.php" class="nav-item active text-dark fw-bold" style="background: #f0f4f8; border-left: 4px solid #0d6efd;"><i class="bi bi-wallet2 text-primary me-2"></i> แจ้งชำระเงิน</a>
    </div>
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5">
    <div class="d-lg-none mb-3">
      <button class="btn btn-light shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="card-header bg-white pt-4 px-4 d-flex align-items-center border-bottom-0">
            <button type="button" class="btn border-0 p-0 me-3 shadow-none" onclick="window.location.href='student_payment.php'"><i class="bi bi-arrow-left fs-4 text-secondary"></i></button>
            <h5 class="fw-bold mb-0" style="color: #2b4d7e;">รายละเอียดสลิปที่รอตรวจสอบ</h5>
          </div>
          
          <div class="card-body p-4 p-md-5">
            <div class="alert rounded-3 mb-4 border text-center" style="background-color: rgba(255, 133, 7, 0.05); border-color: rgba(255, 133, 7, 0.2) !important;">
               <span class="badge px-3 py-2 rounded-pill fw-bold" style="color: rgb(255, 133, 7) !important; font-size: 0.95rem;">
                  <i class="bi bi-hourglass-split me-1"></i> รอตรวจสอบหลักฐานการชำระเงิน
               </span>
            </div>

            <div class="p-3 bg-light rounded-3 mb-4">
              <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                <span class="text-muted fw-bold small">คอร์สเรียน:</span>
                <span class="fw-bold text-dark"><?= htmlspecialchars($billing['name']) . " (" . htmlspecialchars($billing['level']) . ")" ?></span>
              </div>
              <div class="d-flex justify-content-between mb-2 border-bottom pb-2">
                <span class="text-muted fw-bold small">ช่องทางการชำระ:</span>
                <span class="badge bg-success"><?= htmlspecialchars($billing['payment_method']) ?></span>
              </div>
              <div class="d-flex justify-content-between">
                <span class="text-muted fw-bold small">วันเวลาที่นำส่งหลักฐาน:</span>
                <span class="fw-bold text-dark"><?= $payDateTimeStr ?></span>
              </div>
              
              <?php 
                $amountDisplay = (!empty($billing['net_price']) && $billing['net_price'] > 0) ? $billing['net_price'] : floatval($billing['price']);
              ?>
              <hr class="text-muted">
              <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted fw-bold small">ยอดเงินสุทธิที่แจ้งชำระ:</span>
                <span class="fw-bold fs-4 text-primary"><?= number_format($amountDisplay) ?> ฿</span>
              </div>
            </div>

            <div class="mb-4">
              <p class="fw-bold text-dark mb-2 small text-muted">รูปภาพสลิปในระบบ</p>
              <div class="border rounded-3 p-2 bg-white text-center">
                <?php if ($billing['payment_method'] === 'เงินสด' && empty($billing['slip_url'])): ?>
                  <div class="py-4">
                    <i class="bi bi-cash-coin text-success mb-2 d-block" style="font-size: 3.5rem;"></i>
                    <p class="text-muted small mb-0">ชำระด้วยเงินสดที่สถาบันเรียบร้อยแล้ว</p>
                  </div>
                <?php else: ?>
                  <a href="<?= htmlspecialchars($billing['slip_url']) ?>" target="_blank">
                    <img src="<?= htmlspecialchars($billing['slip_url']) ?>" style="max-width: 100%; max-height: 350px; object-fit: contain; border-radius: 8px;">
                  </a>
                <?php endif; ?>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="button" class="btn btn-light border w-100 fw-bold py-2.5" onclick="window.location.href='student_payment.php'" style="border-radius: 8px;">ย้อนกลับ</button>
            </div>
            
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }
</script>
</body>
</html>