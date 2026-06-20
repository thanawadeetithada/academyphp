<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// ตรวจสอบว่ามีการส่งรหัสคอร์สมาหรือไม่ หากไม่มีให้กลับไปหน้าจัดการคอร์ส
if (empty($_GET['courseId'])) {
    header('Location: admin_slips.php');
    exit;
}

$courseId = $_GET['courseId'];
$courseName = $_GET['courseName'] ?? 'ไม่ระบุชื่อคอร์ส';
$duration = $_GET['duration'] ?? '';
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

    <div id="adminSlipsStudentsView">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center">
          <button class="btn me-2" onclick="window.location.href='admin_slips.php'"><i class="bi bi-arrow-left fs-5"></i></button>
          <h4 class="fw-bold mb-0 text-dark">นักเรียนคอร์ส: <span class="text-primary"><?php echo htmlspecialchars($courseName); ?></span></h4>
        </div>
        
        <div>
          <select id="slipMonthFilter" class="form-select bg-white shadow-sm fw-bold" onchange="filterSlipStudentsByMonth()">
            <option value="all">-- แสดงทุกรอบเดือน --</option>
          </select>
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
              <p class="text-muted small mb-4" id="slipDetailCourseName">คอร์ส: <?php echo htmlspecialchars($courseName); ?></p>

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

<div class="modal fade" id="slipAlertModal" tabindex="-1">
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

<div class="modal fade" id="slipConfirmModal" tabindex="-1">
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
        <button type="button" class="btn btn-primary px-4 rounded-pill" onclick="executeSlipStatusUpdate()">ยืนยัน</button>
      </div>
    </div>
  </div>
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

  let currentSlipCourseId = '<?php echo htmlspecialchars($courseId, ENT_QUOTES, 'UTF-8'); ?>';
  let currentSlipCourseName = '<?php echo htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8'); ?>';
  let currentDuration = '<?php echo htmlspecialchars($duration, ENT_QUOTES, 'UTF-8'); ?>';
  let currentSlipStudentsData = [];

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

  // --- Utility Functions ---
  function showSlipLoading(text) {
    const overlay = document.getElementById('slipFullPageLoading');
    document.getElementById('slipLoadingText').innerText = text;
    overlay.style.display = 'flex';
  }

  function hideSlipLoading() {
    document.getElementById('slipFullPageLoading').style.display = 'none';
  }

  // ฟังก์ชัน showSlipAlert อัปเดตใหม่ให้รองรับการเปลี่ยนสีไอคอนและข้อความ
  function showSlipAlert(message, type = 'success') {
    const icon = document.getElementById('slipAlertIcon');
    const title = document.getElementById('slipAlertTitle');

    if (type === 'success') {
      icon.className = 'bi bi-check-circle-fill text-success mb-4 d-block';
      title.innerText = 'ดำเนินการสำเร็จ';
    } else {
      icon.className = 'bi bi-x-circle-fill text-danger mb-4 d-block';
      title.innerText = 'เกิดข้อผิดพลาด';
    }

    document.getElementById('slipAlertMessage').innerText = message;
    const modalEl = document.getElementById('slipAlertModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
  }

  function parseThaiDateStr(dateStr) {
      if (!dateStr) return null;
      let p = String(dateStr).trim().split('/');
      if (p.length === 3) {
          return new Date(parseInt(p[2]) - 543, parseInt(p[1]) - 1, parseInt(p[0]));
      }
      return null;
  }

  // --- Load Data เมื่อเปิดหน้าต่างนี้ ---
  document.addEventListener("DOMContentLoaded", function() {
    loadStudentData();
  });

  function loadStudentData() {
    callAPI('getCourseStudentsForSlips', {courseId: currentSlipCourseId}, 'GET').then(function(students) {
      const tbody = document.getElementById('adminSlipsStudentsTableBody');
      if(students && !students.message) {
        currentSlipStudentsData = students;
        populateSlipMonthDropdown(currentDuration, students);
        filterSlipStudentsByMonth(); 
      } else {
        tbody.innerHTML = `<tr><td colspan="4" class="text-center py-5 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูลนักเรียน</td></tr>`;
      }
    });
  }

  function populateSlipMonthDropdown(duration, students) {
    const select = document.getElementById('slipMonthFilter');
    select.innerHTML = '<option value="all">-- แสดงทุกรอบเดือน --</option>';
    
    let addedMonths = new Set();

    if (duration && duration.includes('-')) {
        let parts = duration.split('-');
        let startD = parseThaiDateStr(parts[0]);
        let endD = parseThaiDateStr(parts[1]);
        
        if (startD && endD) {
            let iter = new Date(startD.getTime());
            let loopGuard = 0;
            const thMonths = ['มค','กพ','มีค','เมย','พค','มิย','กค','สค','กย','ตค','พย','ธค'];
            
            while (iter <= endD && loopGuard < 36) {
                let monthLabel = thMonths[iter.getMonth()];
                
                if (!addedMonths.has(monthLabel)) {
                    let opt = document.createElement('option');
                    opt.value = monthLabel;
                    opt.text = `รอบเดือน ${monthLabel}`;
                    select.appendChild(opt);
                    addedMonths.add(monthLabel);
                }
                
                iter.setMonth(iter.getMonth() + 1);
                loopGuard++;
            }
        }
    }
    
    if (students && students.length > 0) {
        students.forEach(s => {
            if (s.paidMonth && s.paidMonth !== 'รายครั้ง' && !addedMonths.has(s.paidMonth)) {
                let opt = document.createElement('option');
                opt.value = s.paidMonth;
                opt.text = `รอบเดือน ${s.paidMonth}`;
                select.appendChild(opt);
                addedMonths.add(s.paidMonth);
            }
        });
    }

    if (addedMonths.size === 0) {
        select.innerHTML = '<option value="all">คอร์สรายครั้ง (แสดงทั้งหมด)</option>';
        select.style.display = 'none'; 
    } else {
        select.style.display = 'block'; 
    }
  }

  function filterSlipStudentsByMonth() {
    let selectedMonth = document.getElementById('slipMonthFilter').value;
    let filtered = currentSlipStudentsData;
    
    if (selectedMonth !== 'all') {
        filtered = currentSlipStudentsData.filter(s => s.paidMonth === selectedMonth);
    }
    
    displaySlipStudentsList(filtered);
  }

  function displaySlipStudentsList(students) {
    const tbody = document.getElementById('adminSlipsStudentsTableBody');
    tbody.innerHTML = '';

    if (!students || students.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">ไม่พบรายการชำระเงินในรอบเดือนที่เลือก</td></tr>';
      return;
    }

    students.forEach(student => {
      let statusBadge = '';
      let actionBtn = '';
      
      let hasPaymentMethod = (student.slipUrl && student.slipUrl.trim() !== '') || student.paymentMethod === 'เงินสด';
      let monthText = student.paidMonth || 'ไม่ระบุ';

      if (student.paymentStatus === 'approval_payment') {
        statusBadge = `<span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill"><i class="bi bi-check-circle-fill me-1"></i>ชำระแล้ว</span>`;
        actionBtn = `<button class="btn btn-sm btn-outline-success fw-bold px-3" onclick="viewSlipDetail('${student.enrollId}')">รายละเอียด</button>`;
      } else if (student.paymentStatus === 'pending_payment') {
        if (hasPaymentMethod) {
          let btnText = student.paymentMethod === 'เงินสด' ? 'ตรวจสอบ (เงินสด)' : 'ตรวจสอบสลิป';
          let btnClass = student.paymentMethod === 'เงินสด' ? 'btn-success' : 'btn-primary';
          
          // โค้ดสีส้ม Custom ตรงนี้ตามที่คุณขอ
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
          <td class="px-4 py-3 fw-medium text-dark text-nowrap">
            <i class="bi bi-person-circle text-secondary me-2"></i> ${student.studentName}
          </td>
          <td class="py-3 text-center text-nowrap">${monthText}</td>
          <td class="py-3 text-center text-nowrap">${statusBadge}</td>
          <td class="px-4 py-3 text-center text-nowrap">${actionBtn}</td>
        </tr>
      `;
    });
  }

  // --- จัดการดูข้อมูลการชำระของนักเรียน ---
  function viewSlipDetail(enrollId) {
    const student = currentSlipStudentsData.find(s => s.enrollId === enrollId);
    if(!student) return;

    document.getElementById('currentSlipEnrollId').value = enrollId;
    document.getElementById('slipDetailStudentName').innerText = student.studentName;
    document.getElementById('slipDetailMethod').innerText = student.paymentMethod || 'โอนเงิน';
    document.getElementById('slipDetailDate').innerText = student.payDate || '-';
    document.getElementById('slipDetailTime').innerText = student.payTime || '-';
    
    const amountElem = document.getElementById('slipDetailAmount');
    if(amountElem) amountElem.innerText = (student.amount ? Number(student.amount).toLocaleString() : '0') + ' ฿';

    const buttonsContainer = document.getElementById('slipActionButtonsContainer');
    if (student.paymentStatus === 'approval_payment') {
      buttonsContainer.style.setProperty('display', 'none', 'important'); 
    } else {
      buttonsContainer.style.setProperty('display', 'flex', 'important'); 
    }

    const imgContainer = document.getElementById('slipImageContainer');
    
    if (student.paymentMethod === 'เงินสด' && !student.slipUrl) {
      imgContainer.innerHTML = `
        <div class="text-center py-5">
          <i class="bi bi-cash-coin text-success mb-3 d-block" style="font-size: 4rem;"></i>
          <h5 class="fw-bold text-success mb-1">ชำระด้วยเงินสด</h5>
          <p class="text-muted small">รายการนี้ไม่มีการแนบสลิปผ่านออนไลน์</p>
        </div>
      `;
    } 
    else if (student.slipUrl) {
      let url = student.slipUrl;
      let imgHtml = '';
      
      if(url.includes("drive.google.com/file/d/")) {
        let fileId = url.match(/[-\w]{25,}/);
        if(fileId) {
          let previewUrl = "https://drive.google.com/file/d/" + fileId[0] + "/preview";
          imgHtml = `
            <div class="w-100 rounded-3 overflow-hidden" style="border: 1px solid #dee2e6;">
              <iframe src="${previewUrl}" style="width: 100%; height: 400px; border: none;"></iframe>
            </div>
            <div class="mt-3">
              <a href="${url}" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                <i class="bi bi-box-arrow-up-right me-1"></i> เปิดดูรูปภาพแบบเต็มจอ
              </a>
            </div>
          `;
        } else {
          imgHtml = `<a href="${url}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-box-arrow-up-right me-1"></i> คลิกเพื่อเปิดดูรูปภาพ</a>`;
        }
      } else {
        imgHtml = `<a href="${url}" target="_blank"><img src="${url}" alt="Slip" style="max-width: 100%; max-height: 400px; object-fit: contain;"></a>`;
      }
      imgContainer.innerHTML = imgHtml;
    } else {
      imgContainer.innerHTML = `<span class="text-muted">ไม่พบข้อมูลการชำระเงิน</span>`;
    }

    document.getElementById('adminSlipsStudentsView').style.display = 'none';
    document.getElementById('adminSlipsDetailView').style.display = 'block';
    window.scrollTo(0, 0);
  }

  // --- อัปเดตสถานะ ---
  function promptUpdateSlipStatus(newStatus) {
    document.getElementById('tempSlipStatusToUpdate').value = newStatus;
    let msg = newStatus === 'approval_payment' ? 'ยืนยันว่านักเรียนชำระเงินครบถ้วน ถูกต้องใช่หรือไม่?' : 'ต้องการปฏิเสธ และให้นักเรียนแจ้งชำระเงินใหม่ใช่หรือไม่? <br>(ข้อมูลสลิปเดิมจะถูกล้าง)';
    
    // เปลี่ยนมาใช้ innerHTML เพื่อรองรับการขึ้นบรรทัดใหม่ด้วย <br>
    document.getElementById('slipConfirmMessage').innerHTML = msg;
    
    const modalEl = document.getElementById('slipConfirmModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
  }

  function executeSlipStatusUpdate() {
    const modalEl = document.getElementById('slipConfirmModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if(modal) modal.hide();

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