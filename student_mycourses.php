<?php
session_start();
require_once 'db.php';

// ตรวจสอบสิทธิ์ (ยอมรับทั้ง 'student' และ 'นักเรียน')
$role = $_SESSION['sessionRole'] ?? '';
if ($role !== 'student' && $role !== 'นักเรียน') {
    header('Location: index.php');
    exit;
}

// ใช้ userRowId ให้ตรงกับตอน Login
$userId = $_SESSION['userRowId'] ?? ''; 

// ==========================================
// API: จัดการข้อมูลคอร์สที่อนุมัติแล้ว (Backend)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);
    
    $action = $_GET['action'];

    try {
        if ($action === 'getMyCourses') {
            // ดึงข้อมูลคอร์สที่สถานะเป็น "อนุมัติแล้ว" สำหรับนักเรียนคนนี้
            $sql = "SELECT c.*, e.payment_status 
                    FROM enrollments e 
                    JOIN courses c ON e.course_id = c.course_id 
                    WHERE e.user_id = ? AND e.approval_status IN ('approved', 'อนุมัติแล้ว')
                    ORDER BY e.timestamp DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->get_result();
            
            $courses = [];
            while ($row = $result->fetch_assoc()) {
                $pStat = $row['payment_status'];
                
                // ปรับสถานะภาษาอังกฤษจากระบบเก่าให้เป็นภาษาไทย
                if (empty($pStat) || $pStat === 'pending_payment' || $pStat === 'รอชำระเงิน') {
                    $pStat = 'รอชำระ';
                } elseif ($pStat === 'approval_payment' || $pStat === 'approved' || $pStat === 'ชำระแล้ว' || $pStat === 'อนุมัติแล้ว') {
                    $pStat = 'ชำระแล้ว';
                } else if ($pStat === 'รอตรวจสอบ') {
                    $pStat = 'รอตรวจสอบ'; 
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
                    'paymentStatus' => $pStat
                ];
            }
            
            echo json_encode($courses);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
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
    <title>Sci Math Academy - คอร์สเรียนของฉัน</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
    body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .app-layout {
        display: flex;
        min-height: 100vh;
        overflow-x: hidden;
    }

    .sidebar {
        width: 260px;
        transition: all 0.3s;
        flex-shrink: 0;
        z-index: 1000;
    }

    .nav-item {
        display: block;
        padding: 12px 20px;
        margin-bottom: 5px;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.2s;
    }

    .nav-item:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .main-content {
        flex-grow: 1;
        padding: 20px;
        transition: all 0.3s;
        width: 100%;
    }

    .mobile-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(0, 0, 0, 0.5);
        z-index: 999;
    }

    @media (max-width: 991.98px) {
        .sidebar {
            position: fixed;
            left: -260px;
            height: 100vh;
        }

        .sidebar.show {
            left: 0;
        }

        .mobile-overlay.show {
            display: block;
        }
    }

    @media (min-width: 992px) {
        .btn-toggle-menu {
            display: none !important;
        }
    }

    .full-page-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(255, 255, 255, 0.8);
        z-index: 1060;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        visibility: hidden;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .full-page-overlay.show {
        visibility: visible;
        opacity: 1;
    }

    /* Hover Effects */
    .course-card-hover {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }

    .course-card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
    }

    .btn-no-effect:focus,
    .btn-no-effect:active {
        outline: none !important;
        box-shadow: none !important;
        background-color: transparent !important;
        border-color: transparent !important;
    }
    </style>
</head>

<body>

    <div class="app-layout">
        <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>

        <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;"
            id="studentSidebar">
            <div class="sidebar-header border-bottom border-secondary pt-4 pb-3 position-relative">
                <button class="btn text-white position-absolute top-0 end-0 m-2 d-lg-none"
                    style="background: transparent; border: none; font-size: 1.5rem;" onclick="toggleSidebar()">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div class="d-flex align-items-center justify-content-center mb-2">
                    <img src="img/logo.png" onerror="this.src='https://via.placeholder.com/50'"
                        style="width: 50px; height: 50px; object-fit: contain;">
                </div>
                <h5 class="fw-bold mb-0 text-center">Sci Math Academy</h5>
                <div class="text-center"><small style="color: #cbd5e1;">สถาบันสอนพิเศษ สว่างแดนดิน</small></div>
            </div>

            <div class="nav-menu mt-3 flex-grow-1 px-3">
                <a href="main.php" class="nav-item text-white text-opacity-75 text-decoration-none">
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
                <a href="index.php" class="nav-item text-white text-opacity-75 m-0 text-decoration-none"
                    onclick="localStorage.clear();"><i class="bi bi-box-arrow-right me-2"></i> ออกจากระบบ</a>
            </div>
        </aside>

        <main class="main-content pb-5" style="background-color: #f4f6f9;">
            <div class="d-lg-none mb-3">
                <button class="btn btn-light btn-toggle-menu shadow-sm" onclick="toggleSidebar()"><i
                        class="bi bi-list fs-4"></i></button>
            </div>

            <div id="studentMyCourseGridView">
                <div class="d-flex align-items-center mb-4 flex-wrap gap-3">
                    <button class="btn btn-sm px-3 text-secondary border-0 btn-no-effect"
                        onclick="window.location.href='main.php'">
                        <i class="bi bi-arrow-left fs-5"></i>
                    </button>
                    <h4 class="fw-bold mb-0 text-dark">คอร์สเรียนของฉัน</h4>
                </div>

                <div class="row g-4" id="studentMyCourseGrid">
                    <div class="col-12 text-center py-5 text-muted">
                        <span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์สของคุณ...
                    </div>
                </div>
            </div>

            <div id="studentMyCourseDetailView" style="display: none;">
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-xl-8">
                        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                            <div class="position-absolute top-0 start-0 w-100"
                                style="background-color: #2b4d7e; height: 6px;"></div>
                            <div class="card-body p-4 p-md-5" id="myCourseDetailContainer">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>

        <div id="fullPageLoading" class="full-page-overlay">
            <div class="spinner-border text-primary" style="width: 4rem; height: 4rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4 class="mt-3 fw-bold text-primary" id="loadingText">กำลังประมวลผล...</h4>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    const API_URL = 'student_mycourses.php';
    let studentMyCoursesList = [];

    async function callAPI(action) {
        try {
            const response = await fetch(`${API_URL}?action=${action}`);
            return await response.json();
        } catch (error) {
            console.error("API Error:", error);
            return {
                success: false,
                message: error.message
            };
        }
    }

    function toggleSidebar() {
        document.getElementById('studentSidebar').classList.toggle('show');
        document.getElementById('mobileOverlay').classList.toggle('show');
    }

    // === ฟังก์ชันจัดรูปแบบวันที่ ===
    function formatDurationDisplay(durationStr) {
        if (!durationStr) return '';
        let str = String(durationStr);

        const dateStringRegex = /^[A-Za-z]{3}\s([A-Za-z]{3})\s(\d{1,2})\s(\d{4})/;
        let match = str.match(dateStringRegex);
        if (match) {
            const monthMap = {
                "Jan": "01",
                "Feb": "02",
                "Mar": "03",
                "Apr": "04",
                "May": "05",
                "Jun": "06",
                "Jul": "07",
                "Aug": "08",
                "Sep": "09",
                "Oct": "10",
                "Nov": "11",
                "Dec": "12"
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

    // === โหลดข้อมูลตอนเริ่มหน้า ===
    document.addEventListener("DOMContentLoaded", function() {
        renderStudentMyCourses();
    });

    function renderStudentMyCourses() {
        hideMyCourseDetail();

        const grid = document.getElementById('studentMyCourseGrid');
        grid.innerHTML =
            '<div class="col-12 text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลคอร์สของคุณ...</div>';

        callAPI('getMyCourses').then(courses => {
            if (courses && !courses.message) {
                studentMyCoursesList = courses;
                displayStudentMyCourseGrid(studentMyCoursesList);

                // เช็คว่ามีการส่ง Parameter มาให้เปิดคอร์สอัตโนมัติหรือไม่ (เช่น จากหน้า main.php)
                const urlParams = new URLSearchParams(window.location.search);
                const detailId = urlParams.get('viewDetail');
                if (detailId) {
                    showMyCourseDetail(detailId);
                }
            } else {
                grid.innerHTML =
                    '<div class="col-12 text-center py-5 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</div>';
            }
        });
    }

    function displayStudentMyCourseGrid(courses) {
        const grid = document.getElementById('studentMyCourseGrid');
        grid.innerHTML = '';

        if (!courses || courses.length === 0) {
            grid.innerHTML =
                '<div class="col-12 text-center py-5 text-muted">คุณยังไม่มีคอร์สเรียนที่ได้รับการอนุมัติ</div>';
            return;
        }

        courses.forEach(course => {
            let detailsText = course.details ? course.details : 'ไม่มีรายละเอียดเพิ่มเติม';
            let displayDays = course.days ? course.days : '-';
            let displayTime = course.time ? course.time : '-';

            let monthText = course.month ? course.month : '-';
            let yearText = course.yearBE ? course.yearBE : '-';
            let typeDisplayBadge =
                `<span class="badge bg-primary bg-opacity-25 text-primary border px-3 py-2 rounded-pill ms-1">${course.type || 'ไม่ระบุ'} (${monthText} ${yearText})</span>`;

            let formattedDuration = formatDurationDisplay(course.duration);
            let durationDisplay = formattedDuration ?
                `<div class="d-flex align-items-center mt-1"><i class="bi bi-calendar-range text-primary me-2"></i><span class="small fw-bold text-dark">ระยะคอร์ส:</span><span class="small text-muted ms-2">${formattedDuration}</span></div>` :
                '';

            let otherExpenseHtml = '';
            if (course.otherExpenseName && course.otherExpensePrice > 0) {
                otherExpenseHtml = `
        <span class="text-muted small">ค่าใช้จ่ายอื่นๆ</span>
          <div class="d-flex justify-content-between align-items-center mb-0 p-2 rounded-3 mt-2" style="background-color: #e2e8f0; border-left: 3px solid #64748b;">
            <span class="text-muted small fw-bold">${course.otherExpenseName}</span>
            <span class="fw-bold fs-6 text-secondary">+${Number(course.otherExpensePrice).toLocaleString()} ฿</span>
          </div>
        `;
            }

            // ตรวจสอบเงื่อนไข รอชำระ
            let isPendingPayment = (course.paymentStatus === 'รอชำระ' || course.paymentStatus === 'pending_payment');

            let actionButtonHtml = '';
            if (isPendingPayment) {
                actionButtonHtml =
                    `<button class="btn btn-primary w-100 fw-bold " style="border-radius: 8px;" onclick="window.location.href='student_payment.php'; event.stopPropagation();"><i class="bi bi-wallet2 me-1"></i> แจ้งชำระเงิน</button>`;
            } else {
                actionButtonHtml =
                    `<button class="btn btn-primary w-100 fw-bold" style="border-radius: 8px;" onclick="showMyCourseDetail('${course.id}');">รายละเอียด</button>`;
            }

            grid.innerHTML += `
        <div class="col-md-6 col-lg-4">
          <div class="card h-100 border-0 shadow-sm rounded-4 overflow-hidden position-relative course-card-hover" style="cursor: pointer;" onclick="showMyCourseDetail('${course.id}')">
            <div class="position-absolute top-0 start-0 w-100" style="background-color: #2b4d7e; height: 5px;"></div>
            <div class="card-body p-4 d-flex flex-column">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">${course.level}</span>
                  ${typeDisplayBadge}
                </div>
                <span class="badge ${isPendingPayment ? 'bg-warning text-dark' : 'bg-success'}">${course.paymentStatus || 'ไม่ระบุ'}</span>
              </div>
              <h5 class="fw-bold mb-3 text-dark">${course.name}</h5>
              
              <div class="mb-3 px-3 py-2 bg-light rounded-3">
                <div class="d-flex align-items-center mb-1">
                  <i class="bi bi-calendar-check text-primary me-2"></i>
                  <span class="small fw-bold text-dark">วัน:</span>
                  <span class="small text-muted ms-2">${displayDays}</span>
                </div>
                <div class="d-flex align-items-center">
                  <i class="bi bi-clock text-primary me-2"></i>
                  <span class="small fw-bold text-dark">เวลา:</span>
                  <span class="small text-muted ms-2">${displayTime}</span>
                </div>
                ${durationDisplay}
              </div>

              <div class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; min-height: 40px;">
                <i class="bi bi-info-circle me-1"></i> ${detailsText}
              </div>

              <div class="d-flex flex-column mb-4 p-3 rounded-3" style="background-color: #f8faff;">
                <div class="d-flex justify-content-between align-items-center">
                  <span class="text-muted small fw-bold">ราคา</span>
                  <span class="fw-bold fs-5 text-primary">${Number(course.price).toLocaleString()} ฿</span>
                </div>
                  ${otherExpenseHtml}
              </div>

              <div class="mt-auto pt-3 border-top border-light">
                ${actionButtonHtml}
              </div>
            </div>
          </div>
        </div>
      `;
        });
    }

    function showMyCourseDetail(courseId) {
        const course = studentMyCoursesList.find(c => c.id === courseId);
        if (!course) return;

        let detailsText = course.details ? course.details : '<span class="text-muted">ไม่มีรายละเอียดเพิ่มเติม</span>';
        let displayDays = course.days ? course.days : 'ไม่ระบุ';
        let displayTime = course.time ? course.time : 'ไม่ระบุ';

        let monthText = course.month ? course.month : '-';
        let yearText = course.yearBE ? course.yearBE : '-';
        let typeDisplayBadge =
            `<span class="badge bg-primary bg-opacity-25 text-primary border px-3 py-2 rounded-pill ms-1">${course.type || 'ไม่ระบุ'} (${monthText} ${yearText})</span>`;

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

        // ตรวจสอบเงื่อนไข รอชำระ
        let isPendingPayment = (course.paymentStatus === 'รอชำระ' || course.paymentStatus === 'pending_payment');

        let actionButtonHtml = '';
        if (isPendingPayment) {
            actionButtonHtml =
                `<button class="btn btn-warning btn-lg w-100 fw-bold text-dark rounded-3 py-2" onclick="window.location.href='student_payment.php'"><i class="bi bi-wallet2 me-2"></i> ไปยังหน้าแจ้งชำระเงิน</button>`;
        } else {
            actionButtonHtml =
                `<button class="btn btn-primary btn-lg w-100 fw-bold rounded-3 py-2" disabled>ลงทะเบียนแล้ว</button>`;
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

        const detailHtml = `
      <div class="mb-4">
      <button class="btn btn-link text-decoration-none p-0 mb-3 text-secondary" onclick="hideMyCourseDetail()">
        <i class="bi bi-arrow-left me-1 fs-5 px-2 py-1"></i>
      </button><br>
        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill mb-3">${course.level}</span>
        ${typeDisplayBadge}
        <h2 class="fw-bold text-dark mb-0">${course.name}</h2>
      </div>
      
      <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
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

      <div class="mb-4">
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-wallet2 me-2 text-primary"></i>ค่าใช้จ่าย</h6>
        <div class="d-flex justify-content-between align-items-center bg-white border p-3 rounded-3">
           <span class="fw-bold text-muted">ราคาคอร์สเรียน</span>
           <span class="fs-4 fw-bold text-primary">${Number(course.price).toLocaleString()} ฿</span>
        </div>
        ${otherExpenseHtml}
      </div>

      <div class="mb-4 d-flex align-items-center justify-content-between p-3 rounded-3 border" style="background-color: #f8f9fa;">
        <h6 class="mb-0 fw-bold text-dark">สถานะการชำระเงิน</h6>
        <span class="fs-5 fw-bold ${isPendingPayment ? 'text-warning' : 'text-success'}">${course.paymentStatus || 'ไม่ระบุ'}</span>
      </div>

      <div>
        ${actionButtonHtml}
      </div>
    `;

        document.getElementById('myCourseDetailContainer').innerHTML = detailHtml;

        document.getElementById('studentMyCourseGridView').style.display = 'none';
        document.getElementById('studentMyCourseDetailView').style.display = 'block';

        // เคลียร์ URL parameter ออกเมื่อกดเข้ามาดูแล้ว ป้องกันการรีเฟรชแล้วติดหน้าเดิม
        const url = new URL(window.location);
        url.searchParams.delete('viewDetail');
        window.history.replaceState({}, document.title, url);

        window.scrollTo(0, 0);
    }

    function hideMyCourseDetail() {
        document.getElementById('studentMyCourseDetailView').style.display = 'none';
        document.getElementById('studentMyCourseGridView').style.display = 'block';
        window.scrollTo(0, 0);
    }
    </script>
</body>

</html>