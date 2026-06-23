<?php
session_start();
date_default_timezone_set('Asia/Bangkok');
require_once 'db.php';

if ($role = ($_SESSION['sessionRole'] ?? '') !== 'student' && $_SESSION['sessionRole'] !== 'นักเรียน') {
    header('Location: index.php'); exit;
}
$userId = $_SESSION['userRowId'] ?? '';

// ==========================================
// API: บันทึกข้อมูลสลิปแจ้งชำระเงิน (Backend)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'submitPaymentData') {
    header('Content-Type: application/json; charset=utf-8');
    $data = json_decode(file_get_contents("php://input"), true);
    
    try {
        // --- ตรวจสอบวันที่ ห้ามส่งค่าวันในอนาคตมาบันทึก ---
        $currentDate = date('Y-m-d');
        if (isset($data['paymentDate']) && $data['paymentDate'] > $currentDate) {
            echo json_encode(['success' => false, 'message' => 'ไม่สามารถระบุวันที่โอนหรือชำระเงินล่วงหน้าในอนาคตได้']);
            exit;
        }
        // -----------------------------------------

        $isNewRow = $data['isNewRow'] === true || $data['isNewRow'] === 'true';
        $slipUrl = "";
        
        $netPrice = isset($data['amount']) ? (float)$data['amount'] : 0;
        $includeOtherExpense = !empty($data['includeOtherExpense']) ? 1 : 0;

        if (!empty($data['slipFile']) && !empty($data['slipFile']['base64'])) {
            if (!is_dir('uploads/slips')) { mkdir('uploads/slips', 0777, true); }
            $decoded = base64_decode($data['slipFile']['base64']);
            $ext = (strpos($data['slipFile']['mimeType'], 'png') !== false) ? 'png' : 'jpg';
            $filename = 'slip_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $filepath = 'uploads/slips/' . $filename;
            file_put_contents($filepath, $decoded);
            $slipUrl = $filepath;
        }

        $appDate = $data['paymentDate'] . ' ' . $data['paymentTime'] . ':00';

        if ($isNewRow) {
            $enrollId = 'EN' . time() . rand(10, 99);
            $sql = "INSERT INTO enrollments (enroll_id, user_id, course_id, approval_status, payment_status, approved_date, slip_url, paid_month, payment_method, include_other_expense, net_price) 
                    VALUES (?, ?, ?, 'approved', 'pending_payment', ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$enrollId, $userId, $data['courseId'], $appDate, $slipUrl, $data['paidForMonth'], $data['paymentMethod'], $includeOtherExpense, $netPrice]);
        } else {
            $enrollId = $data['enrollId'];
            if (!empty($slipUrl)) {
                $sql = "UPDATE enrollments SET payment_status='pending_payment', approved_date=?, slip_url=?, paid_month=?, payment_method=?, include_other_expense=?, net_price=? WHERE enroll_id=?";
                $stmt = $conn->prepare($sql); 
                $stmt->execute([$appDate, $slipUrl, $data['paidForMonth'], $data['paymentMethod'], $includeOtherExpense, $netPrice, $enrollId]);
            } else {
                $sql = "UPDATE enrollments SET payment_status='pending_payment', approved_date=?, paid_month=?, payment_method=?, include_other_expense=?, net_price=? WHERE enroll_id=?";
                $stmt = $conn->prepare($sql); 
                $stmt->execute([$appDate, $data['paidForMonth'], $data['paymentMethod'], $includeOtherExpense, $netPrice, $enrollId]);
            }
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - แนบหลักฐานชำระเงิน</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  
  <style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .app-layout { display: flex; min-height: 100vh; overflow-x: hidden; }
    .sidebar { width: 260px; transition: all 0.3s; flex-shrink: 0; z-index: 1000; }
    .nav-item { display: block; padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; text-decoration: none; }
    .nav-item:hover { background-color: rgba(255,255,255,0.1); }
    .main-content { flex-grow: 1; padding: 20px; transition: all 0.3s; width: 100%; }
    .mobile-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 999; }
    @media (max-width: 991.98px) { .sidebar { position: fixed; left: -260px; height: 100vh; } .sidebar.show { left: 0; } .mobile-overlay.show { display: block; } }
    .full-page-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(255,255,255,0.8); z-index: 1060; display: flex; flex-direction: column; justify-content: center; align-items: center; visibility: hidden; opacity: 0; transition: opacity 0.3s; }
    .full-page-overlay.show { visibility: visible; opacity: 1; }
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

  <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;" id="studentSidebar">
    <div class="sidebar-header border-bottom border-secondary pt-4 pb-3 text-center position-relative">
      <button class="btn text-white position-absolute top-0 end-0 m-2 d-lg-none" style="background: transparent; border: none; font-size: 1.5rem;" onclick="toggleSidebar()">
        <i class="bi bi-x-lg"></i>
      </button>
      <img src="img/logo.png" onerror="this.src='https://via.placeholder.com/50'" style="width: 50px; height: 50px; object-fit: contain;">
      <h5 class="fw-bold mb-0 mt-2">Sci Math Academy</h5>
    </div>
    <div class="nav-menu mt-3 flex-grow-1 px-3">
      <a href="main.php" class="nav-item text-white text-opacity-75"><i class="bi bi-house-door me-2"></i> หน้าหลัก</a>
      <a href="student_courses.php" class="nav-item text-white text-opacity-75"><i class="bi bi-collection me-2"></i> คอร์สทั้งหมด</a>
      <a href="student_payment.php" class="nav-item active text-dark fw-bold" style="background: #f0f4f8; border-left: 4px solid #0d6efd;"><i class="bi bi-wallet2 text-primary me-2"></i> แจ้งชำระเงิน</a>
    </div>
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5">
    <div class="d-lg-none mb-3">
      <button class="btn btn-light shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-8 col-xl-7">
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
          <div class="card-header bg-white pt-4 px-4 px-md-5 d-flex align-items-center border-bottom-0">
            <button type="button" class="btn border-0 p-0 me-2" onclick="window.location.href='student_payment.php'"><i class="bi bi-arrow-left fs-4 text-secondary"></i></button>
            <h5 class="fw-bold mb-0" style="color: #2b4d7e;"><i class="bi bi-receipt me-2"></i>อัปโหลดสลิปแจ้งชำระเงิน</h5>
          </div>
          <div class="card-body p-4 p-md-5">
            <form id="paymentForm" onsubmit="submitPaymentForm(event)">
              <div class="mb-3">
                <label class="form-label text-muted small fw-bold">ชื่อ-สกุล</label>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($_SESSION['sessionUser'] ?? 'นักเรียน'); ?>" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-bold">คอร์สเรียน</label>
                <input type="text" class="form-control bg-light text-primary fw-bold" id="payCourseDetail" readonly>
              </div>
              <div class="mb-3" id="monthContainer" style="display: none;">
                <label class="form-label text-muted small fw-bold">รอบเดือนที่ชำระ</label>
                <input type="text" class="form-control bg-light fw-bold" id="payForMonth" readonly>
              </div>
              <div class="mb-3" id="otherExpenseContainer" style="display: none;">
                <label class="form-label text-muted small fw-bold">บริการเสริม (ถ้ามี)</label>
                <div class="form-check border p-3 rounded-3 bg-white shadow-sm d-flex align-items-center mb-0" style="cursor: pointer;" onclick="document.getElementById('otherExpenseCheck').click();">
                  <input class="form-check-input me-3 mt-0" type="checkbox" id="otherExpenseCheck" onchange="updateTotalAmount()" onclick="event.stopPropagation();" style="transform: scale(1.3);">
                  <label class="form-check-label w-100" for="otherExpenseCheck" id="otherExpenseLabel" style="cursor: pointer;"></label>
                </div>
              </div>
              
              <div class="row mb-3">
                <div class="col-md-6 mb-3 mb-md-0">
                  <label class="form-label text-muted small fw-bold">วันที่ทำรายการ <span class="text-danger">*</span></label>
                  <div class="position-relative">
                    <input type="hidden" id="payDate" required>
                    <input type="text" id="payDateDisplay" class="form-control bg-white" readonly placeholder="เลือกวันที่" style="cursor: pointer;">
                    <i class="bi bi-calendar-event position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); z-index: 5; color: #6c757d; pointer-events: none;"></i>
                  </div>
                </div>
                <div class="col-md-6">
                  <label class="form-label text-muted small fw-bold">เวลาทำรายการ <span class="text-danger">*</span></label>
                  <input type="time" class="form-control" id="payTime" required>
                </div>
              </div>
              
              <div class="mb-3"><label class="form-label text-muted small fw-bold">จำนวนเงินสุทธิ (บาท) <span class="text-danger">*</span></label><input type="number" class="form-control text-dark fw-bold fs-5 bg-light" id="payAmount" readonly></div>
              <div class="mb-3">
                <label class="form-label text-muted small fw-bold">ช่องทางการชำระเงิน <span class="text-danger">*</span></label>
                <div class="d-flex flex-wrap gap-4 mt-2 p-3">
                  <div class="form-check"><input class="form-check-input" type="radio" name="paymentMethod" id="payMethodTransfer" value="โอนเงิน" checked onchange="updateSlipLabel()"><label class="form-check-label fw-bold" for="payMethodTransfer">โอนเข้าบัญชี</label></div>
                  <div class="form-check"><input class="form-check-input" type="radio" name="paymentMethod" id="payMethodCash" value="เงินสด" onchange="updateSlipLabel()"><label class="form-check-label fw-bold" for="payMethodCash">ชำระเงินสด</label></div>
                </div>

                <div id="qrCodeContainer" class="text-center">
                  <img src="img/payment_qr.png" alt="QR Code" class="img-fluid border rounded-3 shadow-sm" style="max-width: 300px;">
                  <p class="text-muted small mt-2 fw-bold text-primary">สแกน QR Code เพื่อชำระเงิน</p>
                </div>
                </div>
              <div class="mb-4"><label class="form-label text-muted small fw-bold" id="slipLabel">แนบหลักฐานการโอนเงิน (สลิป) <span class="text-danger">*</span></label><input class="form-control" type="file" id="paySlipFile" accept="image/*" required></div>
              <button type="submit" class="btn w-100 fw-bold text-white py-2 fs-6" id="btnSubmitPayment" style="background-color: #2b4d7e; border-radius: 10px;">ยืนยันการแจ้งชำระเงิน</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<div id="fullPageLoading" class="full-page-overlay">
  <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status"></div>
  <h4 class="mt-3 fw-bold text-primary">กำลังประมวลผล...</h4>
</div>

<div class="modal fade" id="successPaymentModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body text-center p-5">
        <i class="bi bi-check-circle-fill text-success mb-4" style="font-size: 4.5rem;"></i>
        <h4 class="fw-bold mb-2 text-dark">แจ้งชำระเงินสำเร็จ</h4>
        <p class="text-muted mb-4">ระบบได้รับข้อมูลการชำระเงินแล้ว กรุณารอแอดมินตรวจสอบ</p>
        <button type="button" class="btn btn-primary px-5 rounded-pill fw-bold" onclick="window.location.href='student_payment.php'">ตกลง</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="errorPaymentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body text-center p-5">
        <i class="bi bi-x-circle-fill text-danger mb-4" style="font-size: 4.5rem;"></i>
        <h4 class="fw-bold mb-2 text-dark">เกิดข้อผิดพลาด</h4>
        <p class="text-muted mb-4" id="errorPaymentMsg">กรุณาลองใหม่อีกครั้ง</p>
        <button type="button" class="btn btn-light px-5 rounded-pill fw-bold border" data-bs-dismiss="modal">ปิด</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/th.js"></script>

<script>
  function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  const urlParams = new URLSearchParams(window.location.search);
  const basePrice = parseFloat(urlParams.get('price')) || 0;
  const otherPrice = parseFloat(urlParams.get('otherExpensePrice')) || 0;

  // ฟังก์ชันช่วยปรับปีใน Modal เป็น พ.ศ.
  function applyBuddhistYear(instance) {
    if (instance.currentYearElement) {
        instance.currentYearElement.value = instance.currentYear + 543;
        instance.currentYearElement.setAttribute('readonly', 'readonly'); // ป้องกันการพิมพ์แก้เพื่อไม่ให้เกิดบั๊กปี
    }
  }

  document.addEventListener("DOMContentLoaded", function() {
    document.getElementById('payCourseDetail').value = `${urlParams.get('courseName')} - ${urlParams.get('level')} (${basePrice.toLocaleString()} บาท)`;
    
    let month = urlParams.get('monthYearText');
    if (month && month !== 'รายครั้ง') {
      document.getElementById('payForMonth').value = month;
      document.getElementById('monthContainer').style.display = 'block';
    }

    let expName = urlParams.get('otherExpenseName');
    if (expName && otherPrice > 0) {
      document.getElementById('otherExpenseLabel').innerHTML = `<span class="text-dark fw-bold">${expName}</span> <span class="text-primary">(+${otherPrice.toLocaleString()} บาท)</span>`;
      document.getElementById('otherExpenseContainer').style.display = 'block';
    }
    
    updateTotalAmount();
    
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('payTime').value = now.toISOString().split('T')[1].slice(0,5);

    // --- เริ่มต้นใช้งาน Flatpickr (สร้างปฏิทินแบบกำหนดเอง) ---
    flatpickr("#payDateDisplay", {
        locale: "th",                  // ใช้ภาษาไทย
        maxDate: "today",              // จำกัดให้เลือกได้ถึงแค่วันปัจจุบัน (แก้ปัญหาวันที่ในอนาคต)
        defaultDate: "today",          // ค่าเริ่มต้นคือวันนี้
        disableMobile: true,           // สำคัญมาก! บังคับใช้หน้าตาปฏิทินของเราแทนปฏิทินพื้นฐานของมือถือ (กันการแสดงเป็น คศ)
        onChange: function(selectedDates, dateStr, instance) {
            if(selectedDates.length > 0) {
                let d = selectedDates[0];
                
                // 1. บันทึกค่า Y-m-d ลงใน hidden input เพื่อส่งหลังบ้าน
                const year = d.getFullYear();
                const month = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                document.getElementById('payDate').value = `${year}-${month}-${day}`;
                
                // 2. แสดงผลในช่อง Input เป็น "วัน เดือน (ภาษาไทย) ปี พ.ศ."
                const monthsTH = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
                document.getElementById('payDateDisplay').value = `${d.getDate()} ${monthsTH[d.getMonth()]} ${year + 543}`;
            }
        },
        onReady: function(selectedDates, dateStr, instance) {
            // โหลดครั้งแรกให้เซ็ตค่าแสดงผลทันที
            this.config.onChange[0](selectedDates, dateStr, instance);
            applyBuddhistYear(instance); // ปรับหัวปฏิทินให้เป็น พ.ศ.
        },
        onYearChange: function(selectedDates, dateStr, instance) {
            applyBuddhistYear(instance);
        },
        onMonthChange: function(selectedDates, dateStr, instance) {
            applyBuddhistYear(instance);
        }
    });
  });

  function updateTotalAmount() {
    let total = basePrice;
    if (document.getElementById('otherExpenseCheck')?.checked) { total += otherPrice; }
    document.getElementById('payAmount').value = total;
  }

  function updateSlipLabel() {
    const isCash = document.querySelector('input[name="paymentMethod"]:checked').value === 'เงินสด';
    
    document.getElementById('slipLabel').innerHTML = isCash ? 'อัปโหลดใบเสร็จ (เว้นว่างได้ถ้าไม่มี)' : 'แนบหลักฐานการโอนเงิน (สลิป) <span class="text-danger">*</span>';
    document.getElementById('paySlipFile').required = !isCash;

    const qrContainer = document.getElementById('qrCodeContainer');
    if (qrContainer) {
      qrContainer.style.display = isCash ? 'none' : 'block';
    }
  }

  function submitPaymentForm(e) {
    e.preventDefault();
    document.getElementById('fullPageLoading').classList.add('show');
    
    const fileInput = document.getElementById('paySlipFile');
    const isExpenseChecked = document.getElementById('otherExpenseCheck') ? document.getElementById('otherExpenseCheck').checked : false;

    const payload = {
      enrollId: urlParams.get('enrollId'), 
      courseId: urlParams.get('courseId'), 
      isNewRow: urlParams.get('isNewRow'),
      paymentDate: document.getElementById('payDate').value, // ดึงจาก hidden input ที่เราเตรียมไว้เป็นแบบ yyyy-mm-dd
      paymentTime: document.getElementById('payTime').value,
      amount: document.getElementById('payAmount').value, 
      includeOtherExpense: isExpenseChecked ? 1 : 0,
      paymentMethod: document.querySelector('input[name="paymentMethod"]:checked').value,
      paidForMonth: document.getElementById('payForMonth').value || 'รายครั้ง', 
      slipFile: null
    };

    if (fileInput.files.length > 0) {
      const file = fileInput.files[0]; const reader = new FileReader();
      reader.onload = function(event) {
        payload.slipFile = { filename: file.name, mimeType: file.type, base64: event.target.result.split(',')[1] };
        sendData(payload);
      };
      reader.readAsDataURL(file);
    } else { sendData(payload); }
  }

  function sendData(payload) {
    fetch('student_submit_payment.php?action=submitPaymentData', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) })
      .then(res => res.json())
      .then(res => {
        document.getElementById('fullPageLoading').classList.remove('show');
        if (res.success) { 
          var succModal = new bootstrap.Modal(document.getElementById('successPaymentModal'));
          succModal.show();
        }
        else { 
          document.getElementById('errorPaymentMsg').innerText = 'เกิดข้อผิดพลาด: ' + res.message;
          var errModal = new bootstrap.Modal(document.getElementById('errorPaymentModal'));
          errModal.show();
        }
      })
      .catch(err => {
        document.getElementById('fullPageLoading').classList.remove('show');
        document.getElementById('errorPaymentMsg').innerText = 'เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์';
        var errModal = new bootstrap.Modal(document.getElementById('errorPaymentModal'));
        errModal.show();
      });
  }
</script>
</body>
</html>