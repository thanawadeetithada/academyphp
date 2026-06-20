<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ (ยอมรับทั้ง 'student' และ 'นักเรียน')
$role = $_SESSION['sessionRole'] ?? '';
if ($role !== 'student' && $role !== 'นักเรียน') {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['userRowId'] ?? ''; 

// ==========================================
// API: ดึงข้อมูลคอร์สของฉัน (Backend)
// ==========================================
if (isset($_GET['action']) && $_GET['action'] === 'getMyCourses') {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);

    try {
        // ใช้ INNER JOIN เพื่อดึงเฉพาะคอร์สที่นักเรียนคนนี้ลงทะเบียนไว้ 
        // และเช็ค deleted_at IS NULL ทั้งในตาราง courses และ enrollments
        $sql = "SELECT c.*, e.approval_status 
                FROM courses c 
                INNER JOIN enrollments e ON c.course_id = e.course_id 
                WHERE e.user_id = ? 
                  AND c.deleted_at IS NULL 
                  AND e.deleted_at IS NULL
                ORDER BY c.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userId]);
        $result = $stmt->get_result();
        
        $courses = [];
        $seenCourseIds = []; // สร้างตัวแปรเก็บ course_id ป้องกันการแสดงคอร์สซ้ำ
        
        while ($row = $result->fetch_assoc()) {
            $cid = $row['course_id'];
            
            // ถ้า course_id นี้ถูกเพิ่มไปแล้ว ให้ข้ามบรรทัดนี้ไปเลย
            if (isset($seenCourseIds[$cid])) {
                continue;
            }
            $seenCourseIds[$cid] = true;

            $status = $row['approval_status'];
            if ($status === 'pending_approval') {
                $statusDisplay = 'รออนุมัติ';
            } else if ($status === 'approved' || $status === 'อนุมัติแล้ว') {
                $statusDisplay = 'อนุมัติแล้ว';
            } else if ($status === 'rejected') {
                $statusDisplay = 'ปฏิเสธ';
            } else {
                $statusDisplay = 'ไม่ทราบสถานะ';
            }

            $courses[] = [
                'id' => $row['course_id'],
                'name' => $row['name'],
                'level' => $row['level'],
                'price' => $row['price'],
                'details' => $row['details'],
                'otherExpenseName' => $row['other_expense_name'] ?? '',
                'otherExpensePrice' => (float)($row['other_expense_price'] ?? 0),
                'days' => $row['days'] ?? '',
                'time' => $row['time'] ?? '',
                'type' => $row['course_type'] ?? 'ไม่ระบุ',
                'month' => $row['course_month'] ?? '',
                'yearBE' => $row['year_be'] ?? '',
                'duration' => $row['duration'] ?? '',
                'studentStatus' => $statusDisplay
            ];
        }
        echo json_encode($courses);
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
  <title>Sci Math Academy - คอร์สของฉัน</title>
  
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
    
    /* Hover Effects */
    .course-card-hover { transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
    .course-card-hover:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

  <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;" id="studentSidebar">
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
      <a href="main.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;">
        <i class="bi bi-house-door me-2"></i> หน้าหลัก
      </a>
      <a href="student_courses.php" class="nav-item text-white text-opacity-75 text-decoration-none">
        <i class="bi bi-collection me-2"></i> คอร์สทั้งหมด
      </a>
      <a href="student_payment.php" class="nav-item text-white text-opacity-75 text-decoration-none">
        <i class="bi bi-wallet2 me-2"></i> แจ้งชำระเงิน
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

    <div id="studentCourseGridView">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h4 class="fw-bold mb-0 text-dark"></i>คอร์สเรียนของฉัน</h4>
      </div>
      
      <div class="row g-4" id="studentCourseGrid">
        <div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์สของฉัน...</div>
      </div>
    </div>

    <div id="studentCourseDetailView" style="display: none;">
      <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-8">
          <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100" style="background-color: #2b4d7e; height: 6px;">
            </div>
            <div class="card-body p-4 p-md-5" id="courseDetailContainer">
              </div>
          </div>
        </div>
      </div>
    </div>

  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'student_mycourses.php';
  let myCoursesList = [];

  function toggleSidebar() {
    document.getElementById('studentSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  function formatDurationDisplay(durationStr) {
    if (!durationStr) return '';
    let str = String(durationStr);

    const dateStringRegex = /^[A-Za-z]{3}\s([A-Za-z]{3})\s(\d{1,2})\s(\d{4})/;
    let match = str.match(dateStringRegex);
    if (match) {
      const monthMap = { 
        "Jan": "01", "Feb": "02", "Mar": "03", "Apr": "04", "May": "05", "Jun": "06", 
        "Jul": "07", "Aug": "08", "Sep": "09", "Oct": "10", "Nov": "11", "Dec": "12" 
      };
      let month = monthMap[match[1]];
      let day = String(match[2]).padStart(2, '0');
      let year = parseInt(match[3]);
      if (year < 2500) year += 543; 
      return `${day}/${month}/${year}`;
    }

    const sqlDateRegex = /^(\d{4})-(\d{2})-(\d{2})$/;
    let sqlMatch = str.match(sqlDateRegex);
    if (sqlMatch) {
       let year = parseInt(sqlMatch[1]);
       if (year < 2500) year += 543; 
       return `${sqlMatch[3]}/${sqlMatch[2]}/${year}`;
    }

    return str;
  }

  document.addEventListener("DOMContentLoaded", function() {
    loadMyCoursesData();
  });

  function loadMyCoursesData() {
    const grid = document.getElementById('studentCourseGrid');
    
    fetch(`${API_URL}?action=getMyCourses`)
      .then(res => res.json())
      .then(courses => {
        if(courses && !courses.message) {
          myCoursesList = courses;
          displayMyCourseGrid(myCoursesList);
        } else {
          grid.innerHTML = '<div class="col-12 text-center py-5 text-danger"><i class="bi bi-exclamation-triangle mb-2" style="font-size: 2rem;"></i><br>เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + (courses.message || 'Unknown Error') + '</div>';
        }
      })
      .catch(error => {
          grid.innerHTML = '<div class="col-12 text-center py-5 text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์</div>';
      });
  }

  function displayMyCourseGrid(courses) {
    const grid = document.getElementById('studentCourseGrid');
    grid.innerHTML = '';

    if (!courses || courses.length === 0) {
      grid.innerHTML = '<div class="col-12 text-center py-5"><i class="bi bi-journal-x text-muted mb-3" style="font-size: 3rem;"></i><h5 class="text-muted fw-bold">คุณยังไม่ได้ลงทะเบียนเรียนคอร์สใดๆ</h5></div>';
      return;
    }

    courses.forEach(course => {
      let detailsText = course.details ? course.details : 'ไม่มีรายละเอียดเพิ่มเติม';
      let displayDays = course.days ? course.days : '-';
      let displayTime = course.time ? course.time : '-';
      let monthText = course.month ? course.month : '-';
      let yearText = course.yearBE ? course.yearBE : '-';
      let typeDisplayBadge = `<span class="badge bg-primary bg-opacity-25 text-primary border px-3 py-2 rounded-pill ms-1">${course.type || 'ไม่ระบุ'}</span>`;
      
      let formattedDuration = formatDurationDisplay(course.duration);
      let durationDisplay = formattedDuration ? `<div class="d-flex align-items-center mt-1"><i class="bi bi-calendar-range me-2"></i><span class="small fw-bold text-dark">ระยะคอร์ส:</span><span class="small text-muted ms-2">${formattedDuration}</span></div>` : '';
      
      // การแสดงสถานะ Badge 
      let statusBadge = '';
      if (course.studentStatus === 'อนุมัติแล้ว') {
        statusBadge = `<span class="badge bg-success text-white px-3 py-2 rounded-pill"><i class="bi bi-check-circle me-1"></i> ${course.studentStatus}</span>`;
      } else if (course.studentStatus === 'รออนุมัติ') {
        statusBadge = `<span class="badge bg-warning text-dark px-3 py-2 rounded-pill"><i class="bi bi-hourglass-split me-1"></i> ${course.studentStatus}</span>`;
      } else {
        statusBadge = `<span class="badge bg-danger text-white px-3 py-2 rounded-pill"><i class="bi bi-x-circle me-1"></i> ${course.studentStatus}</span>`;
      }

      grid.innerHTML += `
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative course-card-hover" style="cursor: pointer;" onclick="showCourseDetail('${course.id}')">
            <div class="position-absolute top-0 start-0 w-100 bg-success" style="height: 5px;"></div>
            <div class="card-body p-4 d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-3">
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
              
              <div class="text-muted small mb-4 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; min-height: 40px;">
                <i class="bi bi-info-circle me-1"></i> ${detailsText}
              </div>

              <div class="d-flex justify-content-between align-items-center mt-auto border-top pt-3">
                <span class="text-muted small fw-bold">สถานะการลงทะเบียน:</span>
                ${statusBadge}
              </div>
            </div>
          </div>
        </div>
      `;
    });
  }

  function showCourseDetail(courseId) {
    const course = myCoursesList.find(c => c.id === courseId);
    if (!course) return;

    let detailsText = course.details ? course.details : '<span class="text-muted">ไม่มีรายละเอียดเพิ่มเติม</span>';
    let displayDays = course.days ? course.days : 'ไม่ระบุ';
    let displayTime = course.time ? course.time : 'ไม่ระบุ';
    let monthText = course.month ? course.month : '-';
    let yearText = course.yearBE ? course.yearBE : '-';
    let typeDisplayBadge = `<span class="badge bg-primary bg-opacity-25 text-primary border px-3 py-2 rounded-pill ms-1">${course.type || 'ไม่ระบุ'}</span>`;
    
    let formattedDurationDetail = formatDurationDisplay(course.duration);
    let durationHtml = '';
    if (formattedDurationDetail) {
       durationHtml = `
         <div class="bg-white border rounded-3 p-3 flex-fill shadow-sm mt-3 mt-sm-0">
            <div class="text-muted small fw-bold mb-1"><i class="bi bi-calendar-range text-primary me-2"></i>ระยะเวลาคอร์ส</div>
            <div class="fw-bold text-dark">${formattedDurationDetail}</div>
         </div>
       `;
    }

    let otherExpenseHtml = '';
    if (course.otherExpenseName && course.otherExpensePrice > 0) {
      otherExpenseHtml = `
        <div class="d-flex justify-content-between align-items-center bg-white border p-3 rounded-3 mt-3">
           <div><span class="text-muted small fw-bold d-block">ค่าใช้จ่ายอื่นๆ</span><span class="fw-bold">${course.otherExpenseName}</span></div>
           <span class="fs-5 fw-bold text-info">+${Number(course.otherExpensePrice).toLocaleString()} ฿</span>
        </div>
      `;
    }

    let statusDisplay = '';
    if (course.studentStatus === 'อนุมัติแล้ว') {
        statusDisplay = `<span class="badge bg-success fs-6 px-4 py-2 rounded-pill"><i class="bi bi-check-circle me-2"></i> อนุมัติแล้ว</span>`;
    } else if (course.studentStatus === 'รออนุมัติ') {
        statusDisplay = `<span class="badge bg-warning text-dark fs-6 px-4 py-2 rounded-pill"><i class="bi bi-hourglass-split me-2"></i> รออนุมัติ</span>`;
    } else {
        statusDisplay = `<span class="badge bg-danger fs-6 px-4 py-2 rounded-pill"><i class="bi bi-x-circle me-2"></i> ${course.studentStatus}</span>`;
    }

    const detailHtml = `
      <div class="mb-4 d-flex justify-content-between align-items-start">
        <div>
          <button class="btn btn-link text-decoration-none p-0 mb-3 text-secondary" onclick="hideCourseDetail()">
            <i class="bi bi-arrow-left me-1 fs-5 fw-bold"></i>
          </button><br>
          <span class="badge bg-light text-dark border px-3 py-2 rounded-pill mb-3">${course.level}</span>
          ${typeDisplayBadge}
          <h2 class="fw-bold text-dark mb-0">${course.name}</h2>
        </div>
        <div class="mt-4 pt-2 text-end">
            ${statusDisplay}
        </div>
      </div>
      
      <div class="d-flex flex-column flex-sm-row gap-3 mb-4 flex-wrap">
        <div class="bg-white border rounded-3 p-3 flex-fill shadow-sm">
           <div class="text-muted small fw-bold mb-1"><i class="bi bi-calendar-check text-primary me-2"></i>วันเรียน</div>
           <div class="fw-bold text-dark">${displayDays}</div>
        </div>
        <div class="bg-white border rounded-3 p-3 flex-fill shadow-sm">
           <div class="text-muted small fw-bold mb-1"><i class="bi bi-clock text-primary me-2"></i>เวลาเรียน</div>
           <div class="fw-bold text-dark">${displayTime}</div>
        </div>
        ${durationHtml}
      </div>

      <div class="p-4 rounded-4 mb-4" style="background-color: #f8faff; border: 1px solid #e2e8f0;">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-journal-text me-2 text-primary"></i>รายละเอียดเนื้อหาคอร์สเรียน</h6>
        <div style="white-space: pre-line; line-height: 1.6; color: #475569;">${detailsText}</div>
      </div>

      <div class="mb-5">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-wallet2 me-2 text-primary"></i>ค่าใช้จ่าย</h6>
        <div class="d-flex justify-content-between align-items-center bg-white border p-3 rounded-3">
           <span class="fw-bold text-muted">ราคาคอร์สเรียน</span>
           <span class="fs-4 fw-bold text-primary">${Number(course.price).toLocaleString()} ฿</span>
        </div>
        ${otherExpenseHtml}
      </div>

      <div class="text-center mt-4">
        <button class="btn btn-secondary px-5 py-2 fw-bold rounded-pill" onclick="hideCourseDetail()">ปิดหน้าต่างนี้</button>
      </div>
    `;

    document.getElementById('courseDetailContainer').innerHTML = detailHtml;
    document.getElementById('studentCourseGridView').style.display = 'none';
    document.getElementById('studentCourseDetailView').style.display = 'block';
    window.scrollTo(0, 0); 
  }

  function hideCourseDetail() {
    document.getElementById('studentCourseDetailView').style.display = 'none';
    document.getElementById('studentCourseGridView').style.display = 'block';
    window.scrollTo(0, 0);
  }
</script>

</body>
</html>