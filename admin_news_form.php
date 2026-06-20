<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$editGroupId = $_GET['groupId'] ?? '';
$isEditMode = !empty($editGroupId);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - <?= $isEditMode ? 'แก้ไขข่าวสาร' : 'เพิ่มกลุ่มข่าวสาร' ?></title>
  
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
    @media (min-width: 992px) { .btn-toggle-menu { display: none !important; } }
    
    .full-page-overlay {
      position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(255,255,255,0.8); z-index: 1060;
      display: flex; flex-direction: column; justify-content: center; align-items: center;
      visibility: hidden; opacity: 0; transition: opacity 0.3s;
    }
    .full-page-overlay.show { visibility: visible; opacity: 1; }
    .icon-btn:hover { background-color: #f0f4f8 !important; border-color: #2b4d7e !important; transform: scale(1.1); transition: 0.2s; }
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
      <a href="admin_slips.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-receipt me-2"></i> จัดการสลิป</a>
      <a href="admin_news.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;">
        <i class="bi bi-megaphone-fill text-primary me-2"></i> ข่าวสาร
      </a>
    </div>
  </aside>

  <main class="main-content pb-5" style="background-color: #f4f6f9;">
    <div class="d-lg-none mb-3">
      <button class="btn btn-light btn-toggle-menu shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div id="newsEditView">
      <div class="d-flex align-items-center mb-4">
        <button class="btn me-1" onclick="window.location.href='admin_news.php'"><i class="bi bi-arrow-left fs-3"></i></button>
        <h4 class="fw-bold mb-0 text-dark" id="newsFormTitle"><?= $isEditMode ? 'แก้ไขกลุ่มข่าวสาร' : 'เพิ่มกลุ่มข่าวสารใหม่' ?></h4>
      </div>

      <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-md-5 bg-light rounded-4">
          <form id="editNewsForm">
            <input type="hidden" id="nGroupId" value="<?= htmlspecialchars($editGroupId) ?>"> 
            
            <div class="bg-white p-4 rounded-4 shadow-sm border mb-4">
              <h6 class="fw-bold text-primary mb-3 border-bottom pb-2"><i class="bi bi-layout-heading me-2"></i>1. ข้อมูลส่วนหัว (Header Group - ปล่อยว่างได้)</h6>
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-bold">ไอคอนกลุ่มหัวข้อ (ปล่อยว่างได้)</label>
                  <div class="input-group" onclick="openIconPicker('nIconHeader', 'nIconHeaderPreview')" style="cursor: pointer;">
                    <span class="input-group-text bg-light"><i id="nIconHeaderPreview" class="bi bi-dash text-secondary"></i></span>
                    <input type="text" class="form-control bg-white" id="nIconHeader" readonly placeholder="คลิกเลือกไอคอน" style="cursor: pointer;">
                  </div>
                </div>
                <div class="col-md-8">
                  <label class="form-label small fw-bold">ชื่อกลุ่มหัวข้อ (ปล่อยว่างได้)</label>
                  <input type="text" class="form-control" id="nHeaderName" placeholder="เช่น ประกาศสำคัญประจำเดือน (ไม่บังคับ)">
                </div>
              </div>
            </div>

            <div class="bg-white p-4 rounded-4 shadow-sm border mb-4">
              <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2 flex-wrap gap-2">
                <h6 class="fw-bold text-success mb-0"><i class="bi bi-card-list me-2"></i>2. เนื้อหาข่าว (เพิ่มข้อความ หรือ รูปภาพได้)</h6>
                <div>
                  <button type="button" class="btn btn-sm btn-outline-info rounded-pill px-3 me-2" onclick="addImageBlock()">
                    <i class="bi bi-image me-1"></i>เพิ่มรูปภาพ
                  </button>
                  <button type="button" class="btn btn-sm btn-outline-success rounded-pill px-3" onclick="addTopicBlock()">
                    <i class="bi bi-plus-circle me-1"></i>เพิ่มเรื่องย่อย
                  </button>
                </div>
              </div>
              
              <div id="topicsContainer">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
              <button type="button" class="btn btn-secondary px-4 rounded-pill" onclick="window.location.href='admin_news.php'">ยกเลิก</button>
              <button type="button" class="btn text-white px-5 rounded-pill" style="background-color: #2b4d7e;" onclick="saveNewsData()">บันทึกข้อมูล</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>

<div class="modal fade" id="iconPickerModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-header border-bottom">
        <h6 class="modal-title fw-bold">เลือกไอคอน <button class="btn btn-sm btn-outline-secondary ms-2" onclick="selectIcon('')">ล้างค่าไอคอน</button></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-4 bg-light">
        <div class="d-flex flex-wrap gap-2 justify-content-center" id="iconGrid"></div>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="newsAlertModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body text-center p-4">
        <i class="bi bi-info-circle text-primary mb-3 d-block" style="font-size: 3rem;"></i>
        <h6 class="fw-bold mb-3" id="newsAlertMessage">ข้อความแจ้งเตือน</h6>
        <button type="button" class="btn btn-primary px-4 rounded-pill w-100" data-bs-dismiss="modal">ตกลง</button>
      </div>
    </div>
  </div>
</div>

<div id="newsFullLoadingOverlay" class="full-page-overlay">
  <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
    <span class="visually-hidden">Loading...</span>
  </div>
  <h4 class="mt-3 fw-bold text-primary" id="newsLoadingText">กำลังประมวลผล...</h4>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'admin_news.php'; // ยิง API กลับไปที่หน้าหลัก
  let newsTopicCounter = 0;
  
  const newsAvailableIcons = [
    'bi-megaphone', 'bi-star', 'bi-bell', 'bi-info-circle', 'bi-exclamation-triangle', 
    'bi-calendar-event', 'bi-book', 'bi-award', 'bi-lightbulb', 'bi-chat-dots', 
    'bi-file-earmark-text', 'bi-pin-angle', 'bi-check-circle', 'bi-x-circle', 
    'bi-heart', 'bi-person', 'bi-house', 'bi-clock', 'bi-trophy', 'bi-shield-check',
    'bi-mortarboard', 'bi-journal-bookmark', 'bi-dash'
  ];

  let newsCurrentIconInputId = '';
  let newsCurrentIconPreviewId = '';
  let currentGroupId = document.getElementById('nGroupId').value;

  // --- API Call Helper ---
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
    document.getElementById('adminSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  function showNewsAlert(message) {
    document.getElementById('newsAlertMessage').innerText = message;
    const modalEl = document.getElementById('newsAlertModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
  }

  function showNewsLoading(text = 'กำลังประมวลผล...') {
    document.getElementById('newsLoadingText').innerText = text;
    document.getElementById('newsFullLoadingOverlay').classList.add('show');
  }

  function hideNewsLoading() {
    document.getElementById('newsFullLoadingOverlay').classList.remove('show');
  }

  function getBase64(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.readAsDataURL(file);
      reader.onload = () => resolve(reader.result.split(',')[1]);
      reader.onerror = error => reject(error);
    });
  }

  function getDirectDriveUrl(url) {
    if (!url) return '';
    if (url.startsWith('uploads/')) return url;

    let fileId = '';
    let matchD = url.match(/\/d\/([a-zA-Z0-9_-]+)/);
    let matchId = url.match(/id=([a-zA-Z0-9_-]+)/);

    if (matchD && matchD[1]) fileId = matchD[1];
    else if (matchId && matchId[1]) fileId = matchId[1];

    if (fileId) return "https://drive.google.com/thumbnail?id=" + fileId + "&sz=w1000";
    if (url.startsWith('http://')) return url.replace('http://', 'https://');
    
    return url;
  }

  // ================= โหลดข้อมูลเมื่อเปิดหน้า =================
  document.addEventListener("DOMContentLoaded", function() {
    if (currentGroupId) {
      showNewsLoading('กำลังดึงข้อมูลข่าวสาร...');
      callAPI('getAllGroupedNews').then(data => {
        hideNewsLoading();
        if (data && !data.message) {
          let group = data.find(g => g.groupId === currentGroupId);
          if (group) {
            populateForm(group);
          } else {
            showNewsAlert("ไม่พบข้อมูลกลุ่มข่าวสารนี้");
            setTimeout(() => window.location.href='admin_news.php', 2000);
          }
        }
      });
    } else {
      addTopicBlock(); // กรณีเพิ่มใหม่ ให้มี Block ข้อความว่างๆ รอไว้ 1 อัน
    }
  });

  function populateForm(group) {
    document.getElementById('nIconHeader').value = group.iconHeader || '';
    if(group.iconHeader) {
      document.getElementById('nIconHeaderPreview').className = `bi ${group.iconHeader} text-primary`;
    } else {
      document.getElementById('nIconHeaderPreview').className = 'bi bi-dash text-secondary';
    }
    
    document.getElementById('nHeaderName').value = group.headerName || '';
    
    document.getElementById('topicsContainer').innerHTML = '';
    group.topics.forEach(t => { 
      if (t.iconTopic && t.iconTopic.toString().startsWith('[IMAGE]')) {
        addImageBlock(t);
      } else {
        addTopicBlock(t); 
      }
    });
  }

  // ================= จัดการ UI ไอคอน =================
  function openIconPicker(inputId, previewId) {
    newsCurrentIconInputId = inputId;
    newsCurrentIconPreviewId = previewId;
    
    const grid = document.getElementById('iconGrid');
    grid.innerHTML = newsAvailableIcons.map(icon => 
      `<button type="button" class="btn btn-white border shadow-sm fs-4 p-2 icon-btn" onclick="selectIcon('${icon}')" style="width: 50px; height: 50px;">
        <i class="bi ${icon} text-dark"></i>
       </button>`
    ).join('');
    
    const modalEl = document.getElementById('iconPickerModal');
    const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    modal.show();
  }

  function selectIcon(iconClass) {
    document.getElementById(newsCurrentIconInputId).value = iconClass;
    let previewEl = document.getElementById(newsCurrentIconPreviewId);
    let colorClass = previewEl.className.split(' ').find(c => c.startsWith('text-')) || 'text-secondary';
    
    if(!iconClass) {
      previewEl.className = `bi bi-dash ${colorClass}`;
    } else {
      previewEl.className = `bi ${iconClass} ${colorClass}`;
    }
    
    bootstrap.Modal.getInstance(document.getElementById('iconPickerModal')).hide();
  }

  // ================= เพิ่มลบ Block ข่าวสาร =================
  function addTopicBlock(data = null) {
    newsTopicCounter++;
    const container = document.getElementById('topicsContainer');
    const div = document.createElement('div');
    div.className = 'topic-block p-3 mb-3 bg-white border rounded-3 position-relative';
    div.id = `topicBlock_${newsTopicCounter}`;
    div.setAttribute('data-type', 'text');

    const iconVal = data && data.iconTopic ? data.iconTopic : '';
    const topicVal = data && data.topic ? data.topic : '';
    const detailVal = data && data.detailTopic ? data.detailTopic : '';
    const displayIconClass = iconVal ? `bi ${iconVal} text-warning` : `bi bi-dash text-secondary`;

    let removeBtn = `<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle" onclick="removeTopicBlock('topicBlock_${newsTopicCounter}')" title="ลบเรื่องนี้"><i class="bi bi-x"></i></button>`;

    div.innerHTML = `
      ${removeBtn}
      <div class="row g-3 pe-4">
        <div class="col-md-4">
          <label class="form-label small fw-bold">ไอคอนเรื่อง (ปล่อยว่างได้)</label>
          <div class="input-group" onclick="openIconPicker('tIcon_${newsTopicCounter}', 'tIconPreview_${newsTopicCounter}')" style="cursor: pointer;">
            <span class="input-group-text bg-light"><i id="tIconPreview_${newsTopicCounter}" class="${displayIconClass}"></i></span>
            <input type="text" class="form-control bg-white topic-icon-input" id="tIcon_${newsTopicCounter}" value="${iconVal}" readonly placeholder="เลือกไอคอน" style="cursor: pointer;">
          </div>
        </div>
        <div class="col-md-8">
          <label class="form-label small fw-bold">เรื่อง (ปล่อยว่างได้)</label>
          <input type="text" class="form-control topic-text-input" value="${topicVal}" placeholder="หัวข้อเรื่องย่อย (ไม่บังคับ)">
        </div>
        <div class="col-12">
          <label class="form-label small fw-bold">รายละเอียด (ปล่อยว่างได้)</label>
          <textarea class="form-control detail-text-input" rows="2" placeholder="รายละเอียดข่าวสาร (ไม่บังคับ)">${detailVal}</textarea>
        </div>
      </div>
    `;
    container.appendChild(div);
  }

  function addImageBlock(data = null) {
    newsTopicCounter++;
    const container = document.getElementById('topicsContainer');
    const div = document.createElement('div');
    div.className = 'topic-block p-3 mb-3 bg-white border rounded-3 position-relative border-info border-2';
    div.id = `topicBlock_${newsTopicCounter}`;
    div.setAttribute('data-type', 'image');

    let url = data && data.iconTopic ? data.iconTopic.replace('[IMAGE]', '') : '';
    let directUrl = getDirectDriveUrl(url); 

    let removeBtn = `<button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-2 rounded-circle" onclick="removeTopicBlock('topicBlock_${newsTopicCounter}')" title="ลบเรื่องนี้"><i class="bi bi-x"></i></button>`;

    div.innerHTML = `
      ${removeBtn}
      <div class="row g-3 pe-4">
        <div class="col-12">
          <label class="form-label small fw-bold text-info"><i class="bi bi-image me-2"></i>อัปโหลดรูปภาพแนบ</label>
          <input type="file" class="form-control topic-image-input mb-2" accept="image/*" onchange="previewImageBlock(this, 'preview_${newsTopicCounter}')">
          <input type="hidden" class="topic-image-url" value="${url}">
          
          <div class="mt-2 text-center bg-light rounded border" style="min-height: 100px; display: flex; align-items: center; justify-content: center; overflow: hidden; padding: 10px;">
            <img id="preview_${newsTopicCounter}" src="${directUrl}" style="max-height: 200px; max-width: 100%; object-fit: contain; display: ${directUrl ? 'block' : 'none'};">
            <span class="text-muted small" style="display: ${directUrl ? 'none' : 'block'};" id="placeholder_${newsTopicCounter}">ยังไม่ได้เลือกรูปภาพ หรือกำลังโหลดรูปภาพเดิม</span>
          </div>
        </div>
      </div>
    `;
    container.appendChild(div);
  }

  function previewImageBlock(input, previewId) {
    const file = input.files[0];
    const preview = document.getElementById(previewId);
    const placeholder = document.getElementById(previewId.replace('preview_', 'placeholder_'));
    const urlInput = input.closest('.topic-block').querySelector('.topic-image-url');
    
    if (file) {
      urlInput.value = ''; // ล้างค่า URL เดิมถ้ามีการเลือกไฟล์ใหม่
      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
      }
      reader.readAsDataURL(file);
    } else if (!urlInput.value) {
      preview.src = '';
      preview.style.display = 'none';
      placeholder.style.display = 'block';
    }
  }

  function removeTopicBlock(id) {
    const blocks = document.querySelectorAll('.topic-block');
    if(blocks.length <= 1) {
      showNewsAlert('ต้องมีอย่างน้อย 1 เรื่อง/รูปภาพ ในกลุ่มข่าวสาร');
      return;
    }
    document.getElementById(id).remove();
  }

  // ================= บันทึกข้อมูลกลับไปยัง API =================
  async function saveNewsData() {
    showNewsLoading('กำลังอัปโหลดและบันทึกข้อมูล...');

    let topicsArray = [];
    const blocks = document.querySelectorAll('.topic-block');
    
    for (let block of blocks) {
      let type = block.getAttribute('data-type');
      
      if (type === 'image') {
        let fileInput = block.querySelector('.topic-image-input');
        let urlInput = block.querySelector('.topic-image-url').value;
        let fileData = null;
        let mimeType = null;
        let fileName = null;
        
        if (fileInput && fileInput.files && fileInput.files.length > 0) {
          let file = fileInput.files[0];
          fileData = await getBase64(file);
          mimeType = file.type;
          fileName = file.name;
        }

        topicsArray.push({
          type: 'image',
          iconTopic: urlInput, 
          topic: '', 
          detailTopic: '', 
          fileData: fileData,
          mimeType: mimeType,
          fileName: fileName
        });
      } else {
        topicsArray.push({
          type: 'text',
          iconTopic: block.querySelector('.topic-icon-input').value,
          topic: block.querySelector('.topic-text-input').value,
          detailTopic: block.querySelector('.detail-text-input').value
        });
      }
    }

    const dataPayload = {
      groupId: document.getElementById('nGroupId').value,
      iconHeader: document.getElementById('nIconHeader').value,
      headerName: document.getElementById('nHeaderName').value,
      topics: topicsArray
    };

    callAPI('saveNewsGroup', dataPayload).then(res => {
      if(res.success) {
        // เมื่อบันทึกสำเร็จ ให้กลับไปที่หน้าหลัก admin_news.php ทันที
        window.location.href = 'admin_news.php';
      } else {
        hideNewsLoading();
        showNewsAlert('เกิดข้อผิดพลาด: ' + res.message);
      }
    });
  }
</script>
</body>
</html>