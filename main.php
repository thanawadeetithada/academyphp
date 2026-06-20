<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ (ยอมรับทั้งค่าภาษาไทยและอังกฤษขึ้นอยู่กับตอน Login)
$role = $_SESSION['sessionRole'] ?? '';
if ($role !== 'student' && $role !== 'นักเรียน') {
    header('Location: index.php');
    exit;
}

$userId = $_SESSION['userRowId'] ?? ''; // รหัส ST-XXXX

// ==========================================
// API: สำหรับนักเรียน (Backend)
// ==========================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    ini_set('display_errors', 0);
    
    $action = $_GET['action'];
    $data = json_decode(file_get_contents("php://input"), true);

    try {
        switch ($action) {
            
            // 1. ดึงข้อมูล Dashboard ของนักเรียน
            case 'getDashboardData':
                // นับจำนวนคอร์สทั้งหมด
                $totalCourses = 0;
                $resTotal = $conn->query("SELECT COUNT(*) FROM courses");
                if ($resTotal) {
                    $totalCourses = $resTotal->fetch_row()[0];
                }

                // ดึงข้อมูลการลงทะเบียนและคอร์สเรียน
                $myCoursesCount = 0;
                $pendingApprovalCount = 0;
                $pendingPaymentCount = 0;
                $pendingVerifyCount = 0;
                $totalStudentEnrollments = 0;
                $enrolledCoursesList = [];

                $sql = "SELECT e.approval_status, e.payment_status, c.course_id, c.name, c.level, c.price, c.details, c.days, c.time, c.duration 
                        FROM enrollments e 
                        JOIN courses c ON e.course_id = c.course_id 
                        WHERE e.user_id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$userId]);
                $enrolls = $stmt->get_result();

                while ($row = $enrolls->fetch_assoc()) {
                    $totalStudentEnrollments++;
                    $appStatus = $row['approval_status'];
                    $payStatus = $row['payment_status'];

                    if ($appStatus === 'pending_approval' || $appStatus === 'pending_approval') {
                        $pendingApprovalCount++;
                    } else if ($appStatus === 'อนุมัติแล้ว' || $appStatus === 'approved') {
                        $myCoursesCount++;
                        
                        // เก็บข้อมูลคอร์สที่กำลังเรียน
                        $enrolledCoursesList[] = [
                            'id' => $row['course_id'],
                            'name' => $row['name'],
                            'level' => $row['level'],
                            'price' => $row['price'],
                            'details' => $row['details'],
                            'days' => $row['days'],
                            'time' => $row['time'],
                            'duration' => $row['duration']
                        ];

                        if ($payStatus === 'pending_payment' || $payStatus === 'pending_payment') {
                            $pendingPaymentCount++;
                        } else if ($payStatus === 'รอตรวจสอบ') {
                            $pendingVerifyCount++;
                        }
                    }
                }

                // ดึงข่าวสารประกาศ (News)
                $newsStmt = $conn->prepare("SELECT * FROM news ORDER BY created_at ASC, id ASC");
                $newsStmt->execute();
                $newsRes = $newsStmt->get_result();
                $groupedNews = [];
                
                while ($nRow = $newsRes->fetch_assoc()) {
                    $gid = $nRow['group_id'];
                    if (!isset($groupedNews[$gid])) {
                        $hName = $nRow['header_name'];
                        if ($hName === '_NO_HEADER_') $hName = '';
                        $groupedNews[$gid] = [
                            'iconHeader' => $nRow['icon_header'],
                            'headerName' => $hName,
                            'topics' => []
                        ];
                    }
                    $groupedNews[$gid]['topics'][] = [
                        'iconTopic' => $nRow['icon_topic'],
                        'topic' => $nRow['topic'],
                        'detailTopic' => $nRow['detail_topic']
                    ];
                }

                echo json_encode([
                    'success' => true,
                    'totalCourses' => $totalCourses,
                    'myCoursesCount' => $myCoursesCount,
                    'pendingApprovalCount' => $pendingApprovalCount,
                    'pendingPaymentCount' => $pendingPaymentCount,
                    'pendingVerifyCount' => $pendingVerifyCount,
                    'totalStudentEnrollments' => $totalStudentEnrollments,
                    'enrolledCoursesList' => $enrolledCoursesList,
                    'newsList' => array_reverse(array_values($groupedNews)) // ข่าวใหม่สุดขึ้นก่อน
                ]);
                break;

            // 2. ดึงข้อมูลส่วนตัว (Profile)
            case 'getProfile':
                $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->get_result()->fetch_assoc();
                
                if ($user) {
                    echo json_encode([
                        'success' => true,
                        'id' => $user['user_id'],
                        'fullName' => $user['full_name'],
                        'nickname' => $user['nickname'],
                        'level' => $user['grade'],
                        'school' => $user['school'],
                        'phone' => $user['phone'],
                        'parentName' => $user['parent_name'],
                        'parentPhone' => $user['parent_phone'],
                        'address' => $user['address']
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูล']);
                }
                break;

            // 3. อัปเดตข้อมูลส่วนตัว (Profile)
            case 'updateProfile':
                $fullName = $data['fullName'];
                $nickname = $data['nickname'];
                $level = $data['level'];
                $school = $data['school'];
                $phone = $data['phone'];
                $parentName = $data['parentName'];
                $parentPhone = $data['parentPhone'];
                $address = $data['address'];
                $password = $data['password'] ?? '';

                if (!empty($password)) {
                    $passHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, nickname=?, grade=?, school=?, phone=?, parent_name=?, parent_phone=?, address=?, password_hash=? WHERE user_id=?");
                    $stmt->execute([$fullName, $nickname, $level, $school, $phone, $parentName, $parentPhone, $address, $passHash, $userId]);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET full_name=?, nickname=?, grade=?, school=?, phone=?, parent_name=?, parent_phone=?, address=? WHERE user_id=?");
                    $stmt->execute([$fullName, $nickname, $level, $school, $phone, $parentName, $parentPhone, $address, $userId]);
                }
                
                // อัปเดต Session
                $_SESSION['sessionUser'] = $fullName;
                
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
  <title>Sci Math Academy - หน้าหลักนักเรียน</title>
  
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
    .course-list-hover { transition: all 0.2s ease-in-out; }
    .course-list-hover:hover { background-color: #f8f9fa; transform: translateX(5px); }
    #payment-status-card[style*="cursor: pointer"]:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    #card-pending-approval[style*="cursor: pointer"]:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    .profile-pill-hover { transition: all 0.2s ease-in-out; }
    .profile-pill-hover:hover { background-color: #f8f9fa !important; transform: translateY(-2px); }
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" onclick="toggleSidebar()"></div>
  
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
        <i class="bi bi-house-door text-primary me-2"></i> หน้าหลัก
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
    
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div class="d-flex align-items-center">
        <button class="btn-toggle-menu d-lg-none me-2" onclick="toggleSidebar()" style="background: transparent; border: none;"><i class="bi bi-list fs-2 text-dark"></i></button>
        <h4 class="fw-bold mb-0 text-dark">ภาพรวมนักเรียน</h4>
      </div>
      
      <div class="d-flex align-items-center bg-white px-3 py-2 rounded-pill shadow-sm border-0 profile-pill-hover" style="cursor: pointer;" onclick="showStudentProfile()">
        <div class="text-end d-none d-sm-block">
          <span class="text-muted small me-1">สวัสดี</span>
          <span class="fw-bold text-dark" style="font-size: 0.9rem;" id="studentNameDisplay">
            <?= htmlspecialchars($_SESSION['sessionUser'] ?? 'นักเรียน'); ?>
          </span>
        </div>
        <i class="bi bi-person-circle ms-2 fs-4 text-primary"></i>
      </div>
    </div>

    <div id="studentDashboardContent">
      <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row align-items-center h-100" style="cursor: pointer;" onclick="window.location.href='student_courses.php'">
            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px; flex-shrink: 0;">
              <i class="bi bi-journal-bookmark fs-4"></i>
            </div>
            <div>
              <h4 class="fw-bold mb-0 text-dark" id="stat-total-courses">-</h4>
              <span class="text-muted" style="font-size: 0.8rem;">คอร์สที่เปิดสอน</span>
            </div>
          </div>
        </div>
        
        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row align-items-center h-100" style="cursor: pointer;" onclick="window.location.href='student_mycourses.php'">
            <div class="bg-success bg-opacity-10 text-success rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px; flex-shrink: 0;">
              <i class="bi bi-journal-check fs-4"></i>
            </div>
            <div>
              <h4 class="fw-bold mb-0 text-dark" id="stat-my-courses">-</h4>
              <span class="text-muted" style="font-size: 0.8rem;">คอร์สของฉัน</span>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row align-items-center h-100" id="card-pending-approval" style="transition: 0.2s;">
            <div class="bg-warning bg-opacity-10 text-warning rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px; flex-shrink: 0;">
              <i class="bi bi-hourglass-split fs-4"></i>
            </div>
            <div>
              <h4 class="fw-bold mb-0 text-dark" id="stat-pending-approval">-</h4>
              <span class="text-muted" style="font-size: 0.8rem;">รอการอนุมัติ</span>
            </div>
          </div>
        </div>

        <div class="col-6 col-md-3">
          <div class="card border-0 shadow-sm rounded-4 p-3 d-flex flex-row align-items-center h-100" style="cursor: pointer;" onclick="window.location.href='student_payment.php'">
            <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 48px; height: 48px; flex-shrink: 0;">
              <i class="bi bi-wallet2 fs-4"></i>
            </div>
            <div>
              <h4 class="fw-bold mb-0 text-dark" id="stat-pending-payment">-</h4>
              <span class="text-muted" style="font-size: 0.8rem;">ยอดค้างชำระ</span>
            </div>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-8">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-calendar-check text-primary me-2"></i>ตารางเรียนวันนี้</h5>
          </div>
          <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
            <div class="list-group list-group-flush" id="today-schedule-list">
              <div class="text-center text-muted p-4"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดตารางเรียน...</div>
            </div>
          </div>

          <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-book text-success me-2"></i>คอร์สที่กำลังเรียน</h5>
          </div>
          <div class="card border-0 shadow-sm rounded-4 p-3" id="enrolled-courses-list">
            <div class="text-center text-muted p-4"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูล...</div>
          </div>
        </div>

        <div class="col-lg-4">
          <h5 style="margin-top: 2.5rem"></h5>
          <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 text-center" id="payment-status-card" style="transition: 0.2s;">
            <div id="payment-status-icon" class="bg-success bg-opacity-10 text-success rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3" style="width: 70px; height: 70px;">
              <i class="bi bi-check-circle fs-1"></i>
            </div>
            <h5 class="fw-bold text-dark" id="payment-status-title">สถานะชำระเงิน</h5>
            <p class="text-muted mb-0" id="payment-status-desc">ตรวจสอบข้อมูล...</p>
          </div>

          <div id="news-cards-container">
            <div class="card border-0 shadow-sm rounded-4 p-4" style="margin-top: 2.5rem">
              <div class="text-center text-muted small pb-3"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดประกาศ...</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div id="studentProfileContent" style="display: none;">
      <div class="row justify-content-center">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="position-absolute top-0 start-0 w-100" style="background-color: #2b4d7e; height: 6px;"></div>
            <div class="card-body p-4 p-md-5">
              
              <div class="d-flex align-items-center mb-4">
                <button class="btn btn-sm rounded-pill px-3" onclick="hideStudentProfile()">
                  <i class="bi bi-arrow-left fs-5"></i>
                </button>
                <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i>ข้อมูลส่วนตัว</h4>
              </div>

              <div id="profileLoading" class="text-center p-5 text-muted">
                <span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูลของคุณ...
              </div>

              <form id="studentProfileForm" style="display: none;">
                <div class="row g-3">
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">รหัสนักเรียน</label>
                    <input type="text" class="form-control bg-light border-0" id="prof-id" readonly>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ระดับชั้น</label>
                    <input type="text" class="form-control" id="prof-level" placeholder="เช่น ม.4, ม.5">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ชื่อ - นามสกุล</label>
                    <input type="text" class="form-control" id="prof-fullname">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ชื่อเล่น</label>
                    <input type="text" class="form-control" id="prof-nickname">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">โรงเรียน</label>
                    <input type="text" class="form-control" id="prof-school">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">เบอร์โทรศัพท์ (นักเรียน)</label>
                    <input type="text" class="form-control" id="prof-phone">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ชื่อผู้ปกครอง</label>
                    <input type="text" class="form-control" id="prof-parentName">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">เบอร์โทรศัพท์ (ผู้ปกครอง)</label>
                    <input type="text" class="form-control" id="prof-parentPhone">
                  </div>
                  <div class="col-12">
                    <label class="form-label text-muted small fw-bold">ที่อยู่</label>
                    <textarea class="form-control" id="prof-address" rows="3"></textarea>
                  </div>

                  <div class="col-12 mt-4">
                    <hr class="text-muted">
                    <h6 class="fw-bold text-dark"><i class="bi bi-key text-secondary me-2"></i>เปลี่ยนรหัสผ่าน <span class="text-muted small fw-normal">(เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</span></h6>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">รหัสผ่านใหม่</label>
                    <div class="position-relative">
                      <input type="password" class="form-control pe-5" id="prof-password">
                      <i class="bi bi-eye text-muted position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem;" onclick="togglePasswordVisibility('prof-password', this)"></i>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label text-muted small fw-bold">ยืนยันรหัสผ่านใหม่</label>
                    <div class="position-relative">
                      <input type="password" class="form-control pe-5" id="prof-confirmPassword">
                      <i class="bi bi-eye text-muted position-absolute" style="right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; font-size: 1.2rem;" onclick="togglePasswordVisibility('prof-confirmPassword', this)"></i>
                    </div>
                    <div id="prof-passwordMismatch" class="text-danger small mt-1 fw-bold" style="display: none;"><i class="bi bi-x-circle me-1"></i>รหัสผ่านไม่ตรงกัน</div>
                  </div>
                  <div class="col-12 text-end mt-4">
                    <button type="button" class="btn btn-primary px-4 rounded-3 fw-bold" id="saveProfileBtn" onclick="saveStudentProfile()">
                      <i class="bi bi-save me-1"></i> บันทึกข้อมูล
                    </button>
                  </div>
                </div>
              </form>

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

  <div class="modal fade" id="successProfileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow rounded-4">
        <div class="modal-body text-center p-5">
          <i class="bi bi-check-circle-fill text-success mb-4" style="font-size: 4.5rem;"></i>
          <h4 class="fw-bold mb-2 text-dark">บันทึกข้อมูลสำเร็จ</h4>
          <p class="text-muted mb-4">ข้อมูลส่วนตัวของคุณได้รับการอัปเดตเรียบร้อยแล้ว</p>
          <button type="button" class="btn btn-primary px-5 rounded-pill fw-bold" data-bs-dismiss="modal" onclick="hideStudentProfile()">ตกลง</button>
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  const API_URL = 'main.php';

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
    return str;
  }

  function goToMyCourseDetail(courseId) {
    window.location.href = `student_mycourses.php?viewDetail=${courseId}`;
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

  // ================= ข้อมูล Dashboard =================
  document.addEventListener("DOMContentLoaded", function() {
    renderStudentMain();
  });

  function renderStudentMain() {
    callAPI('getDashboardData').then(data => {
      if(!data || !data.success) return;
      
      document.getElementById('stat-total-courses').innerText = data.totalCourses;
      document.getElementById('stat-my-courses').innerText = data.myCoursesCount;
      document.getElementById('stat-pending-approval').innerText = data.pendingApprovalCount;
      document.getElementById('stat-pending-payment').innerText = data.pendingPaymentCount + data.pendingVerifyCount;

      // จัดการการ์ด รอการอนุมัติ (Pending Approval)
      const pendingApprovalCard = document.getElementById('card-pending-approval');
      if (data.pendingApprovalCount > 0) {
        pendingApprovalCard.style.cursor = 'pointer';
        pendingApprovalCard.onclick = function() { window.location.href = 'student_mycourses.php'; };
      } else {
        pendingApprovalCard.style.cursor = 'default';
        pendingApprovalCard.onclick = null;
      }

      // จัดการการ์ด สถานะชำระเงิน
      const payCard = document.getElementById('payment-status-card');
      const payIcon = document.getElementById('payment-status-icon');
      const payDesc = document.getElementById('payment-status-desc');

      if (data.totalStudentEnrollments === 0) {
        payIcon.className = "bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3";
        payIcon.innerHTML = '<i class="bi bi-dash-circle fs-1"></i>';
        payDesc.innerText = "ไม่มีสถานะ";
        payDesc.className = "text-muted mb-0";
        payCard.style.cursor = 'default';
        payCard.onclick = null;
      } else if (data.pendingPaymentCount > 0) {
        payIcon.className = "bg-danger bg-opacity-10 text-danger rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3";
        payIcon.innerHTML = '<i class="bi bi-wallet2 fs-1"></i>';
        payDesc.innerText = `มียอดค้างชำระ ${data.pendingPaymentCount} รายการ`;
        payDesc.className = "text-danger fw-bold mb-0";
        payCard.style.cursor = 'pointer';
        payCard.onclick = function() { window.location.href='student_payment.php'; };
      } else if (data.pendingVerifyCount > 0) {
        payIcon.className = "bg-warning bg-opacity-10 text-warning rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3";
        payIcon.innerHTML = '<i class="bi bi-hourglass-split fs-1"></i>';
        payDesc.innerText = `รอแอดมินตรวจสอบสลิป ${data.pendingVerifyCount} รายการ`;
        payDesc.className = "text-warning fw-bold mb-0";
        payCard.style.cursor = 'pointer';
        payCard.onclick = function() { window.location.href='student_payment.php'; };
      } else {
        payIcon.className = "bg-success bg-opacity-10 text-success rounded-circle d-flex justify-content-center align-items-center mx-auto mb-3";
        payIcon.innerHTML = '<i class="bi bi-check-circle fs-1"></i>';
        payDesc.innerText = "ชำระและอนุมัติครบถ้วนแล้ว";
        payDesc.className = "text-muted mb-0";
        payCard.style.cursor = 'default';
        payCard.onclick = null;
      }

      // ตารางเรียนวันนี้
      const todayIndex = new Date().getDay();
      const thaiDays = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
      const todayThai = thaiDays[todayIndex];

      const scheduleContainer = document.getElementById('today-schedule-list');
      scheduleContainer.innerHTML = '';
      
      let todayCourses = data.enrolledCoursesList.filter(c => c.days && c.days.includes(todayThai));
      
      if (todayCourses.length === 0) {
        scheduleContainer.innerHTML = '<div class="text-center text-muted p-4">คุณไม่มีตารางเรียนในวันนี้</div>';
      } else {
        todayCourses.forEach((course, index) => {
          let borderClass = (index === todayCourses.length - 1) ? 'border-0' : 'border-bottom';
          let timeParts = course.time ? course.time.split('-') : [];
          let startTime = timeParts[0] ? timeParts[0].trim() : '--:--';
          let endTime = timeParts[1] ? timeParts[1].trim() : '--:--';
          
          scheduleContainer.innerHTML += `
            <div class="list-group-item p-3 ${borderClass} d-flex align-items-center">
              <div class="bg-primary bg-opacity-10 text-primary rounded-3 text-center py-2 px-3 me-3" style="min-width: 100px;">
                <div class="fw-bold fs-5">${startTime}</div>
                <div style="font-size: 0.75rem;">ถึง ${endTime}</div>
              </div>
              <div class="flex-grow-1">
                <h6 class="fw-bold mb-1 text-dark">${course.name}</h6>
                <div class="text-muted small">วัน${todayThai}</div>
              </div>
            </div>
          `;
        });
      }

      // คอร์สที่กำลังเรียน
      const courseContainer = document.getElementById('enrolled-courses-list');
      courseContainer.innerHTML = '';
      
      if (data.enrolledCoursesList.length === 0) {
        courseContainer.innerHTML = '<div class="text-center text-muted p-4">คุณยังไม่มีคอร์สที่กำลังเรียน</div>';
      } else {
        data.enrolledCoursesList.forEach((course, index) => {
          let borderClass = (index === data.enrolledCoursesList.length - 1) ? '' : 'border-bottom border-light';
          let formattedDuration = formatDurationDisplay(course.duration);
          let durationHtml = formattedDuration ? `<br><i class="bi bi-calendar-range me-1 mt-1"></i> <span class="fw-bold">ระยะคอร์ส:</span> ${formattedDuration}` : '';

          courseContainer.innerHTML += `
            <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center p-3 ${borderClass} gap-3 course-list-hover" style="cursor: pointer; border-radius: 12px;" onclick="goToMyCourseDetail('${course.id}')">
              <div class="flex-grow-1 w-100">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <h6 class="fw-bold text-dark mb-0">${course.name}</h6>
                  <i class="bi bi-chevron-right text-muted"></i>
                </div>
                <div class="text-muted small mb-2"><span class="badge bg-light border text-dark me-2">${course.level}</span> ราคา: ${Number(course.price).toLocaleString()} บาท</div>
                <div class="text-secondary small bg-light p-2 rounded-3">
                  <span class="fw-bold"><i class="bi bi-calendar-event me-1"></i> ${course.days || '-'}</span> <span class="mx-1">|</span> <i class="bi bi-clock me-1"></i> ${course.time || '-'}
                  ${durationHtml}
                  <br><i class="bi bi-info-circle me-1 mt-1"></i> ${course.details || '-'}
                </div>
              </div>
            </div>
          `;
        });
      }

      // ข่าวสาร
      const newsContainer = document.getElementById('news-cards-container');
      newsContainer.innerHTML = '';

      if (!data.newsList || data.newsList.length === 0) {
        newsContainer.innerHTML = `
          <div class="card border-0 shadow-sm rounded-4 p-4" style="margin-top: 2.5rem">
            <div class="text-center text-muted small pb-3">ไม่มีประกาศในขณะนี้</div>
          </div>`;
      } else {
        data.newsList.forEach(group => {
          let topics = group.topics || []; 
          let headerHtml = '';
          
          if (group.headerName || group.iconHeader) {
            let hIcon = group.iconHeader ? `<i class="bi ${group.iconHeader} text-warning me-2"></i>` : '';
            headerHtml = `
              <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                ${hIcon}<span>${group.headerName}</span>
              </h6>
            `;
          }

          let topicsHtml = '';
          topics.forEach((t, index) => {
            let borderClass = (index === topics.length - 1) ? '' : 'border-bottom mb-3 pb-3';
            
            let iconStr = t.iconTopic || '';
            let detailStr = t.detailTopic || '';
            let topicStr = t.topic || '';
            
            if (iconStr.startsWith('[IMAGE]')) {
              let imgUrl = iconStr.replace('[IMAGE]', '');
              let directUrl = getDirectDriveUrl(imgUrl);
              topicsHtml += `
                <div class="${borderClass} text-center py-2">
                  <img src="${directUrl}" class="img-fluid" style="max-height: 200px; width: 100%; object-fit: contain; cursor: pointer;" onclick="window.open('${imgUrl}', '_blank')">
                </div>
              `;
            } else {
              let iconToUse = iconStr || 'bi-dash';
              let displayTopic = topicStr ? `<p class="mb-1 text-dark fw-bold" style="font-size: 0.9rem;">${topicStr}</p>` : '';
              let displayDetail = detailStr ? `<small class="text-muted">${detailStr}</small>` : '';
              
              topicsHtml += `
                <div class="d-flex ${borderClass}">
                  <div class="me-3 mt-1 text-primary"><i class="bi ${iconToUse} fs-5"></i></div>
                  <div>${displayTopic}${displayDetail}</div>
                </div>
              `;
            }
          });

          newsContainer.innerHTML += `
            <div class="card border-0 shadow-sm rounded-4 p-4" style="margin-top: 2.5rem">
              ${headerHtml}
              <div>${topicsHtml}</div>
            </div>
          `;
        });
      }

    });
  }

  // ============== ส่วนของ Profile ==============

  function togglePasswordVisibility(inputId, icon) {
    const inputField = document.getElementById(inputId);
    if (inputField.type === "password") {
      inputField.type = "text";
      icon.classList.remove("bi-eye");
      icon.classList.add("bi-eye-slash");
    } else {
      inputField.type = "password";
      icon.classList.remove("bi-eye-slash");
      icon.classList.add("bi-eye");
    }
  }

  document.addEventListener('DOMContentLoaded', function() {
    const profileForm = document.getElementById('studentProfileForm');
    if(profileForm) {
      profileForm.addEventListener('input', function() {
        const pass = document.getElementById('prof-password').value;
        const confirmPass = document.getElementById('prof-confirmPassword').value;
        const mismatchWarning = document.getElementById('prof-passwordMismatch');
        
        if(confirmPass && pass !== confirmPass) {
          mismatchWarning.style.display = 'block';
          document.getElementById('prof-confirmPassword').classList.add('is-invalid');
        } else {
          mismatchWarning.style.display = 'none';
          document.getElementById('prof-confirmPassword').classList.remove('is-invalid');
        }
      });
    }
  });

  function showStudentProfile() {
    document.getElementById('studentDashboardContent').style.display = 'none';
    document.getElementById('studentProfileContent').style.display = 'block';
    
    document.getElementById('profileLoading').style.display = 'block';
    document.getElementById('studentProfileForm').style.display = 'none';

    document.getElementById('prof-password').value = '';
    document.getElementById('prof-confirmPassword').value = '';
    document.getElementById('prof-passwordMismatch').style.display = 'none';
    document.getElementById('prof-confirmPassword').classList.remove('is-invalid');

    callAPI('getProfile').then(data => {
      document.getElementById('profileLoading').style.display = 'none';
      if(data && data.success) {
        document.getElementById('studentProfileForm').style.display = 'block';
        document.getElementById('prof-id').value = data.id || '';
        document.getElementById('prof-fullname').value = data.fullName || '';
        document.getElementById('prof-nickname').value = data.nickname || '';
        document.getElementById('prof-level').value = data.level || '';
        document.getElementById('prof-school').value = data.school || '';
        document.getElementById('prof-phone').value = data.phone || '';
        document.getElementById('prof-parentName').value = data.parentName || '';
        document.getElementById('prof-parentPhone').value = data.parentPhone || '';
        document.getElementById('prof-address').value = data.address || '';
      } else {
        alert("ไม่พบข้อมูลผู้ใช้งาน");
        hideStudentProfile();
      }
    });
  }

  function hideStudentProfile() {
    document.getElementById('studentProfileContent').style.display = 'none';
    document.getElementById('studentDashboardContent').style.display = 'block';
  }

  function saveStudentProfile() {
    const pass = document.getElementById('prof-password').value;
    const confirmPass = document.getElementById('prof-confirmPassword').value;

    if (pass || confirmPass) {
      if (pass !== confirmPass) {
        alert('รหัสผ่านใหม่ไม่ตรงกัน กรุณาตรวจสอบอีกครั้ง');
        document.getElementById('prof-confirmPassword').focus();
        return;
      }
    }

    const profileData = {
      fullName: document.getElementById('prof-fullname').value,
      nickname: document.getElementById('prof-nickname').value,
      level: document.getElementById('prof-level').value,
      school: document.getElementById('prof-school').value,
      phone: document.getElementById('prof-phone').value,
      parentName: document.getElementById('prof-parentName').value,
      parentPhone: document.getElementById('prof-parentPhone').value,
      address: document.getElementById('prof-address').value,
      password: pass 
    };

    showLoading('กำลังบันทึกข้อมูลของคุณ...');

    callAPI('updateProfile', profileData).then(res => {
      hideLoading();
      if(res && res.success) {
        document.getElementById('studentNameDisplay').innerText = profileData.fullName;
        
        document.getElementById('prof-password').value = '';
        document.getElementById('prof-confirmPassword').value = '';

        var successModal = new bootstrap.Modal(document.getElementById('successProfileModal'));
        successModal.show();
      } else {
        alert('เกิดข้อผิดพลาด: ' + (res.message || 'Unknown Error'));
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