<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

// ==========================================
// API: จัดการข้อมูลข่าวสาร (Backend)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0); 
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        switch ($action) {
            
            // 1. ดึงข้อมูลข่าวสารทั้งหมด (จัดกลุ่ม)
            case 'getAllGroupedNews':
                $stmt = $conn->prepare("SELECT * FROM news ORDER BY created_at ASC, id ASC");
                $stmt->execute();
                $result = $stmt->get_result();
                
                $grouped = [];
                while ($row = $result->fetch_assoc()) {
                    $gid = $row['group_id'];
                    
                    if (!isset($grouped[$gid])) {
                        $headerName = $row['header_name'];
                        if ($headerName === '_NO_HEADER_') $headerName = '';
                        
                        $grouped[$gid] = [
                            'groupId' => $gid,
                            'iconHeader' => $row['icon_header'],
                            'headerName' => $headerName,
                            'topics' => []
                        ];
                    }
                    
                    $grouped[$gid]['topics'][] = [
                        'id' => $row['id'],
                        'iconTopic' => $row['icon_topic'],
                        'topic' => $row['topic'],
                        'detailTopic' => $row['detail_topic']
                    ];
                }
                
                // สลับให้ข่าวใหม่สุดขึ้นก่อน
                $output = array_values($grouped);
                echo json_encode(array_reverse($output));
                break;

            // 2. บันทึก / อัปเดตกลุ่มข่าวสาร
            case 'saveNewsGroup':
                $groupId = !empty($data['groupId']) ? $data['groupId'] : 'G_' . time() . rand(100, 999);
                
                // ลบข้อมูลเดิมในกลุ่มทิ้งก่อน (ถ้าเป็นการแก้ไข)
                if (!empty($data['groupId'])) {
                    $stmt = $conn->prepare("DELETE FROM news WHERE group_id = ?");
                    $stmt->execute([$groupId]);
                }
                
                $iconHeader = $data['iconHeader'] ?? '';
                $headerName = $data['headerName'] ?? '';
                if ($iconHeader === '' && $headerName === '') {
                    $headerName = '_NO_HEADER_';
                }

                $topics = $data['topics'] ?? [];
                
                // สร้างโฟลเดอร์เก็บรูปภาพ (ถ้ายังไม่มี)
                if (!is_dir('uploads/news')) {
                    mkdir('uploads/news', 0777, true);
                }

                $stmt = $conn->prepare("INSERT INTO news (group_id, icon_header, header_name, icon_topic, topic, detail_topic) VALUES (?, ?, ?, ?, ?, ?)");
                
                foreach ($topics as $t) {
                    $iconTopic = $t['iconTopic'] ?? '';
                    
                    // จัดการอัปโหลดรูปภาพ
                    if (isset($t['type']) && $t['type'] === 'image') {
                        if (!empty($t['fileData'])) {
                            $decoded = base64_decode($t['fileData']);
                            $ext = 'jpg';
                            if (strpos($t['mimeType'], 'png') !== false) $ext = 'png';
                            elseif (strpos($t['mimeType'], 'gif') !== false) $ext = 'gif';
                            elseif (strpos($t['mimeType'], 'webp') !== false) $ext = 'webp';
                            
                            $filename = 'news_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                            $filepath = 'uploads/news/' . $filename;
                            
                            file_put_contents($filepath, $decoded);
                            $iconTopic = '[IMAGE]' . $filepath;
                        } else {
                            if (empty($iconTopic)) $iconTopic = '[IMAGE]';
                            elseif (strpos($iconTopic, '[IMAGE]') !== 0) $iconTopic = '[IMAGE]' . $iconTopic;
                        }
                    }

                    $topic = $t['topic'] ?? '';
                    $detailTopic = $t['detailTopic'] ?? '';
                    
                    $stmt->execute([$groupId, $iconHeader, $headerName, $iconTopic, $topic, $detailTopic]);
                }
                
                echo json_encode(['success' => true]);
                break;

            // 3. ลบกลุ่มข่าวสาร
            case 'deleteNewsGroup':
                $groupId = $data['groupId'];
                
                // ลบรูปภาพออกจากเซิร์ฟเวอร์ก่อน
                $stmt = $conn->prepare("SELECT icon_topic FROM news WHERE group_id = ?");
                $stmt->execute([$groupId]);
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $ic = $row['icon_topic'];
                    if (strpos($ic, '[IMAGE]uploads/') === 0) {
                        $fileToDelete = str_replace('[IMAGE]', '', $ic);
                        if (file_exists($fileToDelete)) unlink($fileToDelete);
                    }
                }

                // ลบข้อมูลจากฐานข้อมูล
                $stmt = $conn->prepare("DELETE FROM news WHERE group_id = ?");
                $stmt->execute([$groupId]);
                
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
  <title>Sci Math Academy - จัดการข่าวสาร</title>
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
    
    <div class="p-3 border-top border-secondary">
      <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none" onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
    </div>
  </aside>

  <main class="main-content pb-5" style="background-color: #f4f6f9;">
    <div class="d-lg-none mb-3">
      <button class="btn btn-light btn-toggle-menu shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div id="newsTableView">
      <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-megaphone text-primary me-2"></i>จัดการข่าวสารประกาศ</h4>
        <button class="btn text-white px-4 shadow-sm text-nowrap" style="background-color: #2b4d7e; border-radius: 8px;" onclick="window.location.href='admin_news_form.php'">
          <i class="bi bi-plus-lg"></i> เพิ่มกลุ่มข่าวสาร
        </button>
      </div>

      <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
              <tr class="text-nowrap">
                <th class="py-3 px-4 fw-bold border-0" style="width: 25%;">กลุ่มหัวข้อ (Header)</th>
                <th class="py-3 fw-bold border-0" style="width: 30%;">เรื่อง (Topic)</th>
                <th class="py-3 fw-bold border-0" style="width: 35%;">รายละเอียด (Detail)</th>
                <th class="py-3 px-4 fw-bold border-0 text-center">จัดการ</th>
              </tr>
            </thead>
            <tbody id="newsTableBody" style="border-top: none;">
              <tr><td colspan="4" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลข่าวสาร...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
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

<div class="modal fade" id="deleteNewsConfirmModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow rounded-4">
      <div class="modal-body text-center p-4 pb-2">
        <i class="bi bi-exclamation-triangle text-danger mb-3 d-block" style="font-size: 3rem;"></i>
        <h5 class="fw-bold mb-2">ยืนยันการลบข้อมูล</h5>
        <p class="text-muted mb-4">คุณต้องการลบ <strong>"กลุ่มข่าวสารนี้พร้อมเรื่องย่อยทั้งหมด"</strong> ใช่หรือไม่? การกระทำนี้ไม่สามารถกู้คืนได้</p>
        <input type="hidden" id="deleteGroupIdTemp">
      </div>
      <div class="modal-footer border-0 d-flex justify-content-center pt-0 pb-4 gap-2">
        <button type="button" class="btn btn-light px-4 rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="button" class="btn btn-danger px-4 rounded-pill" onclick="confirmDeleteNewsGroup()">ใช่, ลบข้อมูล</button>
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
  const API_URL = 'admin_news.php';
  let groupedNewsData = [];

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

  document.addEventListener("DOMContentLoaded", function() {
    loadNewsData();
  });

  function loadNewsData() {
    callAPI('getAllGroupedNews').then(data => {
      groupedNewsData = data || [];
      displayNewsTable(groupedNewsData);
    });
  }

  function displayNewsTable(newsData) {
    const tbody = document.getElementById('newsTableBody');
    tbody.innerHTML = '';
    
    if(!newsData || newsData.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">กดปุ่มเพิ่มกลุ่มข่าวสารเพื่อเริ่มต้น...</td></tr>';
      return;
    }

    newsData.forEach(group => {
      let headerText = '';
      if (group.headerName || group.iconHeader) {
        let hIcon = group.iconHeader ? `<i class="bi ${group.iconHeader} me-2 text-primary fs-5"></i>` : '';
        headerText = `<div class="fw-bold text-dark">${hIcon}${group.headerName}</div>`;
      } else {
        headerText = `<div class="text-muted fst-italic small">ไม่มีหัวข้อกลุ่ม</div>`;
      }
      
      let topicList = '';
      let detailList = '';
      
      group.topics.forEach((t, i) => {
        let borderClass = i < group.topics.length - 1 ? 'border-bottom border-light pb-2 mb-2' : '';
        
        if (t.iconTopic && t.iconTopic.startsWith('[IMAGE]')) {
          let url = t.iconTopic.replace('[IMAGE]', '');
          let directUrl = getDirectDriveUrl(url); 
          
          topicList += `<div class="${borderClass} text-info"><i class="bi bi-image me-1"></i> <strong>รูปภาพแนบ</strong></div>`;
          
          if(directUrl) {
            detailList += `<div class="${borderClass} text-center py-1">
                            <img src="${directUrl}" class="img-fluid rounded border shadow-sm" style="max-height: 120px; cursor: pointer; object-fit: contain;" onclick="window.open('${url}', '_blank')">
                           </div>`;
          } else {
            detailList += `<div class="${borderClass} text-muted fst-italic">ไม่มีรูปภาพ</div>`;
          }
        } else {
          let iconToUse = t.iconTopic || 'bi-dash';
          let topicText = t.topic ? t.topic : '<span class="text-muted fst-italic">-</span>';
          let detailText = t.detailTopic ? t.detailTopic : '<span class="text-muted fst-italic">-</span>';
          
          topicList += `<div class="${borderClass} text-dark"><i class="bi ${iconToUse} me-1 text-warning"></i> <strong>${topicText}</strong></div>`;
          detailList += `<div class="${borderClass} text-muted">${detailText}</div>`;
        }
      });

      tbody.innerHTML += `
        <tr>
          <td class="px-4 py-3 align-top bg-light" style="border-right: 1px solid #e2e8f0;">${headerText}</td>
          <td class="py-3 align-top">${topicList}</td>
          <td class="py-3 align-top" style="max-width: 300px; white-space: normal;">${detailList}</td>
          <td class="px-4 py-3 text-center align-middle">
            <button class="btn btn-sm btn-outline-primary me-2 shadow-sm" onclick="window.location.href='admin_news_form.php?groupId=${group.groupId}'" title="แก้ไขกลุ่มนี้"><i class="bi bi-pencil-square"></i></button>
            <button class="btn btn-sm btn-outline-danger shadow-sm" onclick="promptDeleteGroup('${group.groupId}')" title="ลบกลุ่มนี้"><i class="bi bi-trash"></i></button>
          </td>
        </tr>
      `;
    });
  }

  let newsDeleteModalInstance;
  function promptDeleteGroup(groupId) {
    document.getElementById('deleteGroupIdTemp').value = groupId;
    const modalEl = document.getElementById('deleteNewsConfirmModal');
    newsDeleteModalInstance = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
    newsDeleteModalInstance.show();
  }

  function confirmDeleteNewsGroup() {
    if(newsDeleteModalInstance) newsDeleteModalInstance.hide();
    const groupId = document.getElementById('deleteGroupIdTemp').value;
    
    showNewsLoading('กำลังลบข้อมูล...');
    
    callAPI('deleteNewsGroup', { groupId: groupId }).then(res => {
      if(res.success) {
        hideNewsLoading();
        loadNewsData(); // รีเฟรชตาราง
      } else {
        hideNewsLoading();
        showNewsAlert('เกิดข้อผิดพลาด: ' + res.message);
      }
    });
  }
</script>
</body>
</html>