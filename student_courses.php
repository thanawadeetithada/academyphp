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
// API: จัดการข้อมูลคอร์สเรียนของนักเรียน (Backend)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        switch ($action) {
            
            // 1. ดึงข้อมูลคอร์สทั้งหมด พร้อมเช็คสถานะการลงทะเบียนของนักเรียนคนนี้
            case 'getAllCoursesForStudent':
                // เพิ่มการดึง e.net_price ออกมาด้วย
                $sql = "SELECT c.*, e.approval_status, e.net_price 
                        FROM courses c 
                        LEFT JOIN enrollments e ON c.course_id = e.course_id AND e.user_id = ? AND e.deleted_at IS NULL
                        WHERE c.deleted_at IS NULL
                        ORDER BY c.created_at DESC";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$userId]);
                $result = $stmt->get_result();
                
                $courses = [];
                $seenCourseIds = []; // สร้างตัวแปรเก็บ course_id ที่ถูกเพิ่มไปแล้ว
                
                while ($row = $result->fetch_assoc()) {
                    $cid = $row['course_id'];
                    
                    // ถ้า course_id นี้ถูกนำเข้า Array ไปแล้ว ให้ข้าม (ป้องกันคอร์สแสดงซ้ำ)
                    if (isset($seenCourseIds[$cid])) {
                        continue;
                    }
                    $seenCourseIds[$cid] = true;

                    $status = $row['approval_status'];
                    if (empty($status)) {
                        $status = 'not_registered';
                    } else if ($status === 'pending_approval') {
                        $status = 'pending_approval';
                    } else if ($status === 'approved') {
                        $status = 'อนุมัติแล้ว';
                    } else if ($status === 'rejected') {
                        $status = 'ปฏิเสธ';
                    }

                    $courses[] = [
                        'id' => $row['course_id'],
                        'name' => $row['name'],
                        'level' => $row['level'],
                        'price' => $row['price'],
                        'netPrice' => isset($row['net_price']) ? (float)$row['net_price'] : 0, // ส่งค่า net_price ไปให้ JS
                        'details' => $row['details'],
                        'otherExpenseName' => $row['other_expense_name'] ?? '',
                        'otherExpensePrice' => (float)($row['other_expense_price'] ?? 0),
                        'days' => $row['days'] ?? '',
                        'time' => $row['time'] ?? '',
                        'type' => $row['course_type'] ?? 'ไม่ระบุ',
                        'month' => $row['course_month'] ?? '',
                        'yearBE' => $row['year_be'] ?? '',
                        'duration' => $row['duration'] ?? '',
                        'studentStatus' => $status
                    ];
                }
                echo json_encode($courses);
                break;

            // 2. นักเรียนกดลงทะเบียนคอร์สเรียนใหม่
            case 'registerCourse':
                $courseId = $data['courseId'];

                // ตรวจสอบก่อนว่าเคยลงทะเบียนคอร์สนี้ไปแล้วหรือยัง (เช็คเฉพาะที่ยังไม่โดน Soft Delete)
                $checkStmt = $conn->prepare("SELECT enroll_id FROM enrollments WHERE user_id = ? AND course_id = ? AND deleted_at IS NULL");
                $checkStmt->execute([$userId, $courseId]);
                if ($checkStmt->get_result()->num_rows > 0) {
                    echo json_encode(['success' => false, 'message' => 'คุณได้ส่งคำขอลงทะเบียนคอร์สนี้ไปแล้ว']);
                    exit;
                }

                // บันทึกคำขอลงทะเบียน
                $enrollId = 'EN' . time() . rand(10, 99);
                $sql = "INSERT INTO enrollments (enroll_id, user_id, course_id, approval_status, payment_status) VALUES (?, ?, ?, 'pending_approval', 'pending_payment')";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$enrollId, $userId, $courseId]);
                
                echo json_encode(['success' => true]);
                break;

            // 3. นักเรียนกดยกเลิกการลงทะเบียน (ทำได้เฉพาะตอน 'pending_approval')
            case 'cancelCourse':
                $courseId = $data['courseId'];

                $checkStmt = $conn->prepare("SELECT approval_status FROM enrollments WHERE user_id = ? AND course_id = ? AND deleted_at IS NULL");
                $checkStmt->execute([$userId, $courseId]);
                $res = $checkStmt->get_result();
                
                if ($res->num_rows > 0) {
                    $row = $res->fetch_assoc();
                    $status = $row['approval_status'];
                    
                    if ($status === 'pending_approval') {
                        $delStmt = $conn->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ? AND approval_status = 'pending_approval'");
                        $delStmt->execute([$userId, $courseId]);
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'ไม่สามารถยกเลิกได้เนื่องจากสถานะเปลี่ยนไปแล้ว']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'ไม่พบรายการลงทะเบียนนี้']);
                }
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
  <title>Sci Math Academy - คอร์สทั้งหมด</title>
  
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
    
    .full-page-overlay {
      display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(255,255,255,0.8); z-index: 1060;
      flex-direction: column; justify-content: center; align-items: center;
      transition: opacity 0.3s;
    }
    .full-page-overlay.show { display: flex; }

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
      <a href="main.php" class="nav-item text-white text-opacity-75 text-decoration-none">
        <i class="bi bi-house-door me-2"></i> หน้าหลัก
      </a>
      <a href="student_courses.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;">
        <i class="bi bi-collection text-primary me-2"></i> คอร์สทั้งหมด
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
      <button class="btn btn-light shadow-sm" onclick="toggleSidebar()"><i class="bi bi-list fs-4"></i></button>
    </div>

    <div id="studentCourseGridView">
      <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <h4 class="fw-bold mb-0 text-dark">คอร์สเรียนทั้งหมดที่เปิดสอน</h4>
      </div>
      
      <div class="row g-4" id="studentCourseGrid">
        <div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์ส...</div>
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

  <div class="modal fade" id="registerCourseConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-body text-center p-5">
          <i class="bi bi-check-circle text-primary mb-4" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mb-2">ยืนยันการลงทะเบียนเรียน</h5>
          <p class="text-muted">คุณต้องการลงทะเบียนคอร์ส <br><span id="regCourseLabelName" class="fw-bold text-dark fs-5"></span><br> ใช่หรือไม่?</p>
          <input type="hidden" id="regCourseTargetId">
          <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 8px;">ยกเลิก</button>
            <button type="button" class="btn btn-primary px-4" onclick="confirmRegisterCourse()" style="border-radius: 8px;">ยืนยันการลงทะเบียน</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="cancelCourseConfirmModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-body text-center p-5">
          <i class="bi bi-x-circle text-danger mb-4" style="font-size: 3.5rem;"></i>
          <h5 class="fw-bold mb-2">ยกเลิกการลงทะเบียน</h5>
          <p class="text-muted">คุณต้องการยกเลิกคำขอลงทะเบียนคอร์ส <br><span id="cancelCourseLabelName" class="fw-bold text-dark fs-5"></span><br> ใช่หรือไม่?</p>
          <input type="hidden" id="cancelCourseTargetId">
          <div class="d-flex justify-content-center gap-2 mt-4">
            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal" style="border-radius: 8px;">ปิด</button>
            <button type="button" class="btn btn-danger px-4" onclick="confirmCancelCourse()" style="border-radius: 8px;">ยืนยันยกเลิก</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="fullPageLoading" class="full-page-overlay">
    <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
      <span class="visually-hidden">Loading...</span>
    </div>
    <h4 class="mt-3 fw-bold text-primary" id="loadingText">กำลังประมวลผล...</h4>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'student_courses.php';
  let studentCoursesList = [];
  let registerModalInstance;
  let cancelModalInstance;

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
    loadStudentCoursesData();
  });

  function loadStudentCoursesData() {
    const grid = document.getElementById('studentCourseGrid');
    grid.innerHTML = '<div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์ส...</div>';
    
    callAPI('getAllCoursesForStudent').then(courses => {
      if(courses && !courses.message) {
        
        const uniqueCourses = [];
        const seen = new Set();
        
        courses.forEach(course => {
          if (!seen.has(course.id)) {
            seen.add(course.id);
            uniqueCourses.push(course);
          }
        });
        
        studentCoursesList = uniqueCourses;
        displayStudentCourseGrid(studentCoursesList);
      } else {
        grid.innerHTML = '<div class="col-12 text-center py-5 text-danger"><i class="bi bi-exclamation-triangle mb-2" style="font-size: 2rem;"></i><br>เกิดข้อผิดพลาดในการโหลดข้อมูล: ' + (courses.message || 'Unknown Error') + '</div>';
      }
    });
  }

  function displayStudentCourseGrid(courses) {
    const grid = document.getElementById('studentCourseGrid');
    grid.innerHTML = '';

    if (!courses || courses.length === 0) {
      grid.innerHTML = '<div class="col-12 text-center py-5 text-muted">ยังไม่มีคอร์สเรียนในระบบ</div>';
      return;
    }

    courses.forEach(course => {
      let detailsText = course.details ? course.details : 'ไม่มีรายละเอียดเพิ่มเติม';
      
      let displayDays = course.days ? course.days : '-';
      let displayTime = course.time ? course.time : '-';
      let typeDisplayBadge = `<span class="badge bg-primary bg-opacity-25 text-primary border px-3 py-2 rounded-pill ms-1">${course.type || 'ไม่ระบุ'}</span>`;
      
      let formattedDuration = formatDurationDisplay(course.duration);
      let durationDisplay = formattedDuration ? `<div class="d-flex align-items-center mt-1"><i class="bi bi-calendar-range me-2"></i><span class="small fw-bold text-dark">ระยะคอร์ส:</span><span class="small text-muted ms-2">${formattedDuration}</span></div>` : '';
      
      let safeCourseName = course.name ? String(course.name).replace(/'/g, "\\'").replace(/"/g, '"') : '';
      
      // การตั้งค่าราคาที่จะแสดงตามสถานะ
      let displayPrice = course.price;
      let priceLabel = 'ราคา';
      let priceColorClass = 'text-primary';

      if (course.studentStatus === 'อนุมัติแล้ว' && course.netPrice > 0) {
          displayPrice = course.netPrice;
          priceLabel = 'ราคาสุทธิ (ชำระแล้ว)';
          priceColorClass = 'text-success'; // เปลี่ยนสีให้เห็นชัดว่าจ่ายแล้ว
      }

      let actionButtonHtml = '';
      if (course.studentStatus === 'not_registered') {
        actionButtonHtml = `<button class="btn btn-primary w-100 fw-bold" style="border-radius: 8px;" onclick="promptRegisterCourse('${course.id}', '${safeCourseName}'); event.stopPropagation();"><i class="bi bi-pencil-square me-1"></i> ลงทะเบียนเรียน</button>`;
      } else if (course.studentStatus === 'pending_approval') {
        actionButtonHtml = `<button class="btn btn-warning w-100 fw-bold text-dark" style="border-radius: 8px;" onclick="promptCancelCourse('${course.id}', '${safeCourseName}'); event.stopPropagation();"><i class="bi bi-hourglass-split me-1"></i> รออนุมัติ (ยกเลิก)</button>`;
      } else if (course.studentStatus === 'อนุมัติแล้ว') {
        actionButtonHtml = `<button class="btn btn-success w-100 fw-bold" style="border-radius: 8px;" disabled><i class="bi bi-check-circle me-1"></i> อนุมัติแล้ว</button>`;
      } else if (course.studentStatus === 'ปฏิเสธ') {
        actionButtonHtml = `<button class="btn btn-danger w-100 fw-bold" style="border-radius: 8px;" disabled><i class="bi bi-x-circle me-1"></i> ถูกปฏิเสธ</button>`;
      }

      grid.innerHTML += `
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative course-card-hover" style="cursor: pointer;" onclick="showCourseDetail('${course.id}')">
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
              
              <div class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; min-height: 40px;">
                <i class="bi bi-info-circle me-1"></i> ${detailsText}
              </div>

              <div class="d-flex justify-content-between align-items-center mb-4 p-3 rounded-3" style="background-color: #e2e8f0;">
                <span class="text-muted small fw-bold">${priceLabel}</span>
                <span class="fw-bold fs-5 ${priceColorClass}">${Number(displayPrice).toLocaleString()} ฿</span>
              </div>
              
              <div class="mt-auto">
                ${actionButtonHtml}
              </div>
            </div>
          </div>
        </div>
      `;
    });
  }

  function showCourseDetail(courseId) {
    const course = studentCoursesList.find(c => c.id === courseId);
    if (!course) return;

    let safeCourseName = course.name ? String(course.name).replace(/'/g, "\\'").replace(/"/g, '"') : '';
    let detailsText = course.details ? course.details : '<span class="text-muted">ไม่มีรายละเอียดเพิ่มเติม</span>';
    let displayDays = course.days ? course.days : 'ไม่ระบุ';
    let displayTime = course.time ? course.time : 'ไม่ระบุ';
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

    let actionButtonHtml = '';
    if (course.studentStatus === 'not_registered') {
      actionButtonHtml = `<button class="btn btn-primary btn-lg w-100 fw-bold rounded-3 py-3" onclick="promptRegisterCourse('${course.id}', '${safeCourseName}')"><i class="bi bi-pencil-square me-2"></i> ลงทะเบียนเรียนคอร์สนี้</button>`;
    } else if (course.studentStatus === 'pending_approval') {
      actionButtonHtml = `<button class="btn btn-warning btn-lg w-100 fw-bold text-dark rounded-3 py-2" onclick="promptCancelCourse('${course.id}', '${safeCourseName}')"><i class="bi bi-hourglass-split me-2"></i> รออนุมัติ (ยกเลิก)</button>`;
    } else if (course.studentStatus === 'อนุมัติแล้ว') {
      actionButtonHtml = `<button class="btn btn-success btn-lg w-100 fw-bold rounded-3 py-2" disabled><i class="bi bi-check-circle me-2"></i> อนุมัติแล้ว</button>`;
    } else if (course.studentStatus === 'ปฏิเสธ') {
      actionButtonHtml = `<button class="btn btn-danger btn-lg w-100 fw-bold rounded-3 py-2" disabled><i class="bi bi-x-circle me-2"></i> คำขอถูกปฏิเสธ</button>`;
    }

    // จัดการส่วนแสดงราคาในหน้า Detail
    let priceSectionHtml = '';
    
    if (course.studentStatus === 'อนุมัติแล้ว' && course.netPrice > 0) {
        // ถ้าอนุมัติแล้ว โชว์กล่องราคาสุทธิกล่องเดียว
        priceSectionHtml = `
            <div class="d-flex justify-content-between align-items-center bg-white border border-success p-3 rounded-3">
               <span class="fw-bold text-success">ราคาสุทธิ (ชำระแล้ว)</span>
               <span class="fs-4 fw-bold text-success">${Number(course.netPrice).toLocaleString()} ฿</span>
            </div>
        `;
    } else {
        // ถ้ายังไม่อนุมัติ โชว์ราคาตั้งต้น + บริการเสริม
        let otherExpenseHtml = '';
        if (course.otherExpenseName && course.otherExpensePrice > 0) {
          otherExpenseHtml = `
            <div class="d-flex justify-content-between align-items-center bg-white border p-3 rounded-3 mt-3">
               <div><span class="text-muted small fw-bold d-block">ค่าใช้จ่ายอื่นๆ (ทางเลือก)</span><span class="fw-bold">${course.otherExpenseName}</span></div>
               <span class="fs-5 fw-bold text-info">+${Number(course.otherExpensePrice).toLocaleString()} ฿</span>
            </div>
          `;
        }
        
        priceSectionHtml = `
            <div class="d-flex justify-content-between align-items-center bg-white border p-3 rounded-3">
               <span class="fw-bold text-muted">ราคาคอร์สเรียน</span>
               <span class="fs-4 fw-bold text-primary">${Number(course.price).toLocaleString()} ฿</span>
            </div>
            ${otherExpenseHtml}
        `;
    }

    const detailHtml = `
      <div class="mb-4">
      <button class="btn btn-link text-decoration-none p-0 mb-3 text-secondary" onclick="hideCourseDetail()">
        <i class="bi bi-arrow-left me-1 fs-5 fw-bold"></i>
      </button><br>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill mb-3">${course.level}</span>
        ${typeDisplayBadge}
        <h2 class="fw-bold text-dark mb-0">${course.name}</h2>
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
        ${priceSectionHtml}
      </div>

      <div>
        ${actionButtonHtml}
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

  function promptRegisterCourse(id, name) {
    document.getElementById('regCourseTargetId').value = id;
    document.getElementById('regCourseLabelName').innerText = name;
    registerModalInstance = new bootstrap.Modal(document.getElementById('registerCourseConfirmModal'));
    registerModalInstance.show();
  }

  function confirmRegisterCourse() {
    const courseId = document.getElementById('regCourseTargetId').value;
    
    registerModalInstance.hide();
    showLoading('กำลังส่งคำขอลงทะเบียน...');
    
    callAPI('registerCourse', { courseId: courseId }).then(res => {
      hideLoading();
      if(res.success) {
        hideCourseDetail();
        loadStudentCoursesData(); 
      } else {
        alert('ลงทะเบียนไม่สำเร็จ: ' + res.message);
      }
    });
  }

  function promptCancelCourse(id, name) {
    document.getElementById('cancelCourseTargetId').value = id;
    document.getElementById('cancelCourseLabelName').innerText = name;
    cancelModalInstance = new bootstrap.Modal(document.getElementById('cancelCourseConfirmModal'));
    cancelModalInstance.show();
  }

  function confirmCancelCourse() {
    const courseId = document.getElementById('cancelCourseTargetId').value;
    
    cancelModalInstance.hide();
    showLoading('กำลังยกเลิกคำขอ...');
    
    callAPI('cancelCourse', { courseId: courseId }).then(res => {
      hideLoading();
      if(res.success) {
        hideCourseDetail(); 
        loadStudentCoursesData(); 
      } else {
        alert('ยกเลิกไม่สำเร็จ: ' + res.message);
      }
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