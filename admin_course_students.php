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
    header('Location: admin_courses.php');
    exit;
}

$courseId = $_GET['courseId'];
$courseName = $_GET['courseName'] ?? 'ไม่ระบุชื่อคอร์ส';
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - รายชื่อนักเรียน</title>
  
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
      <div class="fw-bold text-primary align-self-start align-self-md-auto bg-primary bg-opacity-10 px-3 py-2 rounded">
        <i class="bi bi-person-badge me-1"></i> ผู้ดูแลระบบ (<?php echo htmlspecialchars($_SESSION['sessionUser'] ?? 'Admin'); ?>)
      </div>
    </div>

    <div id="courseStudentsView">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div class="d-flex align-items-center">
          <button class="btn me-2" onclick="window.location.href='admin_courses.php'"><i class="bi bi-arrow-left fs-5"></i></button>
          <h4 class="fw-bold mb-0 text-dark">รายชื่อนักเรียน: <span id="currentViewCourseName" class="text-primary"><?php echo htmlspecialchars($courseName); ?></span></h4>
        </div>
        <button class="btn text-white fw-medium px-4 py-2 shadow-sm" style="background-color: #0d4ba5; border-radius: 8px;" onclick="openAddStudentModal()">
          <i class="bi bi-person-plus-fill me-1"></i> เพิ่มผู้เรียน
        </button>
      </div>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr class="text-nowrap">
                <th class="py-3 px-4 fw-bold border-0">ชื่อ-สกุล (ชื่อเล่น)</th>
                <th class="py-3 fw-bold border-0">ชั้น</th>
                <th class="py-3 fw-bold border-0">โรงเรียน</th>
                <th class="py-3 fw-bold border-0">เบอร์โทร</th>
                <th class="py-3 fw-bold border-0 text-center">สถานะ</th>
                <th class="py-3 px-4 fw-bold border-0 text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody id="courseStudentsTableBody" style="border-top: none;">
              <tr><td colspan="6" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลนักเรียน...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-header bg-light border-bottom pt-4 pb-3 px-4">
          <div>
            <h5 class="modal-title fw-bold text-dark mb-1">เพิ่มนักเรียนลงในคอร์ส</h5>
            <small class="text-muted">รายชื่อนักเรียนในระบบที่ยังไม่ได้เปิดลงทะเบียนวิชานี้</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4 bg-white">
          <div class="input-group mb-3 shadow-sm border-0 rounded-3 overflow-hidden">
            <span class="input-group-text bg-light border-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" class="form-control bg-light border-0" id="searchStudentInput" placeholder="ค้นหาชื่อ, โรงเรียน หรือเบอร์โทร..." onkeyup="filterAvailableStudents(this.value)">
          </div>
          <div class="form-check mb-3 ms-2">
            <input class="form-check-input" type="checkbox" id="selectAllStudents" onclick="toggleSelectAll(this)">
            <label class="form-check-label fw-bold text-primary" style="cursor: pointer;" for="selectAllStudents">เลือกทั้งหมด</label>
          </div>
          <div class="list-group" id="availableStudentsList"></div>
        </div>
        <div class="modal-footer bg-light border-top border-light d-flex justify-content-between align-items-center">
          <span class="text-muted small fw-bold" id="selectedStudentCount">เลือกแล้ว 0 คน</span>
          <div>
            <button type="button" class="btn btn-light border-secondary me-2" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="button" class="btn text-white fw-medium px-4" style="background-color: #0d4ba5; border-radius: 8px;" onclick="confirmAddMultipleStudents()">เพิ่มนักเรียน</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="editStudentStatusModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-header border-bottom-0 pb-0 pt-4 px-4">
          <h5 class="modal-title fw-bold text-dark">ปรับเปลี่ยนสถานะนักเรียน</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <input type="hidden" id="statusTargetStudentRowId">
          <p class="text-muted mb-3">แก้ไขสถานะวิชาเรียนของ: <span id="statusTargetStudentName" class="fw-bold text-dark"></span></p>
          <div class="mb-4">
            <label class="form-label small fw-bold text-muted">เลือกสถานะ</label>
            <select class="form-select form-control-lg bg-light border-0" id="studentStatusSelect">
              <option value="pending_approval">รออนุมัติ</option>
              <option value="approved">อนุมัติแล้ว</option>
              <option value="rejected">ปฏิเสธ</option>
            </select>
          </div>
          <button type="button" class="btn text-white w-100 py-2 fw-bold" style="background-color: #2b4d7e; border-radius: 8px;" onclick="saveStudentStatus()">อัปเดตสถานะ</button>
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

<script>
  // จุดเด่นคือ เรายังคงยิง API ไปที่หน้า admin_courses.php เหมือนเดิม
  const API_URL = 'admin_courses.php'; 
  
  let currentActiveCourseId = '<?php echo htmlspecialchars($courseId, ENT_QUOTES, 'UTF-8'); ?>'; 
  let currentActiveCourseName = '<?php echo htmlspecialchars($courseName, ENT_QUOTES, 'UTF-8'); ?>'; 

  let addStudentModalInstance;
  let editStudentStatusModalInstance;
  
  let availableStudentsData = [];
  let selectedStudentIds = []; 

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

  document.addEventListener("DOMContentLoaded", function() {
    viewCourseStudents(); // โหลดข้อมูลทันทีเมื่อเปิดหน้า
  });

  // โหลดรายชื่อนักเรียนในคอร์ส
  function viewCourseStudents() {
    callAPI('getCourseStudents', { courseId: currentActiveCourseId }, 'GET').then(function(students) {
      displayCourseStudentsList(students);
    });
  }

  function displayCourseStudentsList(students) {
    const tbody = document.getElementById('courseStudentsTableBody');
    tbody.innerHTML = '';

    if (!students || students.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">ยังไม่มีนักเรียนลงทะเบียนในคอร์สนี้</td></tr>';
      return;
    }

    const statusMap = {
      'pending_approval': { text: 'รออนุมัติ', class: 'bg-warning-subtle text-warning' },
      'approved': { text: 'อนุมัติแล้ว', class: 'bg-success-subtle text-success' },
      'rejected': { text: 'ปฏิเสธ', class: 'bg-danger-subtle text-danger' }
    };

    students.forEach(student => {
      let stInfo = statusMap[student.status] || { text: student.status, class: 'bg-secondary-subtle text-secondary' };
      let statusBadge = `<span class="badge ${stInfo.class} px-3 py-2 rounded-pill">${stInfo.text}</span>`;

      tbody.innerHTML += `
        <tr>
          <td class="px-4 py-3 fw-medium text-dark text-nowrap">${student.fullName} (${student.nickname})</td>
          <td class="py-3 text-nowrap">${student.grade}</td>
          <td class="py-3 text-nowrap">${student.school}</td>
          <td class="py-3 text-nowrap">${student.phone}</td>
          <td class="py-3 text-center text-nowrap">${statusBadge}</td>
          <td class="px-4 py-3 text-center text-nowrap">
            <button class="btn btn-sm btn-outline-primary" title="แก้ไขสถานะ" onclick="openEditStudentStatusModal('${student.id}', '${student.fullName}', '${student.status}')"><i class="bi bi-pencil-square me-1"></i> ปรับสถานะ</button>
          </td>
        </tr>
      `;
    });
  }

  // ==================== การจัดการสถานะ ====================
  function openEditStudentStatusModal(rowId, name, currentStatus) {
    document.getElementById('statusTargetStudentRowId').value = rowId;
    document.getElementById('statusTargetStudentName').innerText = name;
    document.getElementById('studentStatusSelect').value = currentStatus;
    
    editStudentStatusModalInstance = new bootstrap.Modal(document.getElementById('editStudentStatusModal'));
    editStudentStatusModalInstance.show();
  }

  function saveStudentStatus() {
    const rowId = document.getElementById('statusTargetStudentRowId').value;
    const newStatus = document.getElementById('studentStatusSelect').value;
    
    editStudentStatusModalInstance.hide();
    showLoading('กำลังอัปเดตสถานะ...');
    
    callAPI('updateStudentCourseStatus', { rowId: rowId, courseId: currentActiveCourseId, newStatus: newStatus }, 'POST')
      .then(function(res) {
        hideLoading();
        if(res.success) viewCourseStudents(); 
        else alert('เกิดข้อผิดพลาด: ' + res.message);
      });
  }

  // ==================== การเพิ่มนักเรียน ====================
  function openAddStudentModal() {
    selectedStudentIds = [];
    document.getElementById('searchStudentInput').value = '';
    document.getElementById('selectAllStudents').checked = false;
    updateSelectedCountUI();

    const listDiv = document.getElementById('availableStudentsList');
    listDiv.innerHTML = '<div class="text-center py-4 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังค้นหารายชื่อ...</div>';
    
    if(!addStudentModalInstance) {
      addStudentModalInstance = new bootstrap.Modal(document.getElementById('addStudentModal'));
    }
    addStudentModalInstance.show();

    callAPI('getAvailableStudents', { courseId: currentActiveCourseId }, 'GET').then(function(students) {
      availableStudentsData = students;
      displayAvailableStudents(availableStudentsData);
    });
  }

  function displayAvailableStudents(students) {
    const listDiv = document.getElementById('availableStudentsList');
    listDiv.innerHTML = '';

    if(students.length === 0) {
      listDiv.innerHTML = '<div class="text-center py-4 text-muted">ไม่พบข้อมูลนักเรียน หรือทุกคนลงทะเบียนคอร์สนี้แล้ว</div>';
      return;
    }

    students.forEach(student => {
      let isChecked = selectedStudentIds.includes(student.id) ? 'checked' : '';
      listDiv.innerHTML += `
        <label class="list-group-item list-group-item-action d-flex align-items-center py-3 border-0 border-bottom" style="cursor: pointer;">
          <input class="form-check-input mt-0 me-3 student-checkbox" type="checkbox" value="${student.id}" ${isChecked} onchange="toggleStudentSelection('${student.id}', this.checked)">
          <div>
            <h6 class="mb-1 fw-bold text-dark">${student.fullName} <span class="text-muted fw-normal">(${student.nickname})</span></h6>
            <small class="text-muted"><i class="bi bi-building me-1"></i>${student.school} | <i class="bi bi-telephone ms-2 me-1"></i>${student.phone}</small>
          </div>
        </label>
      `;
    });
  }

  function filterAvailableStudents(keyword) {
    keyword = String(keyword).toLowerCase().trim();
    const filtered = availableStudentsData.filter(s => 
      String(s.fullName || '').toLowerCase().includes(keyword) ||
      String(s.nickname || '').toLowerCase().includes(keyword) ||
      String(s.school || '').toLowerCase().includes(keyword) ||
      String(s.phone || '').toLowerCase().includes(keyword)
    );
    displayAvailableStudents(filtered);
  }

  function toggleStudentSelection(id, isChecked) {
    if (isChecked) {
      if (!selectedStudentIds.includes(id)) selectedStudentIds.push(id);
    } else {
      selectedStudentIds = selectedStudentIds.filter(val => val !== id);
      document.getElementById('selectAllStudents').checked = false; 
    }
    updateSelectedCountUI();
  }

  function toggleSelectAll(masterCheckbox) {
    const checkboxes = document.querySelectorAll('.student-checkbox');
    checkboxes.forEach(cb => {
      cb.checked = masterCheckbox.checked;
      let id = cb.value;
      if (masterCheckbox.checked) {
        if (!selectedStudentIds.includes(id)) selectedStudentIds.push(id);
      } else {
        selectedStudentIds = selectedStudentIds.filter(val => val !== id);
      }
    });
    updateSelectedCountUI();
  }

  function updateSelectedCountUI() {
    document.getElementById('selectedStudentCount').innerText = `เลือกแล้ว ${selectedStudentIds.length} คน`;
  }

  function confirmAddMultipleStudents() {
    if (selectedStudentIds.length === 0) {
      alert("กรุณาเลือกนักเรียนอย่างน้อย 1 คน");
      return;
    }

    showLoading(`กำลังเพิ่มนักเรียน ${selectedStudentIds.length} คน ลงคอร์ส...`);
    addStudentModalInstance.hide();
    
    callAPI('addMultipleStudentsToCourse', { rowIds: selectedStudentIds, courseId: currentActiveCourseId }, 'POST')
      .then(function(res) {
        hideLoading();
        if(res.success) viewCourseStudents(); 
        else alert('เกิดข้อผิดพลาด: ' + res.message);
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