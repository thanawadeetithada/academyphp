<?php
session_start();
require_once 'db.php'; 

// ตรวจสอบสิทธิ์ ต้องเป็น admin เท่านั้น
if (!isset($_SESSION['sessionRole']) || $_SESSION['sessionRole'] !== 'admin') {
    header('Location: index.php');
    exit;
}

/// ==========================================
// API: ส่งข้อมูล Dashboard ให้กับ Frontend
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_data') {
    header('Content-Type: application/json');
    
    // ตั้งค่า Array เดือนภาษาไทย
    $monthNames = ["ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $now = new DateTime('first day of this month');
    $currentMonthKey = $now->format('Y-m');

    $chartKeys = [];
    $chartLabels = [];
    $chartDataMap = [];

    // สร้าง Label สำหรับกราฟ 6 เดือนย้อนหลัง
    for ($i = 5; $i >= 0; $i--) {
        $d = clone $now;
        $d->modify("-$i months");
        $key = $d->format('Y-m');
        $monthIndex = (int)$d->format('n') - 1;
        $yearTh = (int)$d->format('Y') + 543;
        
        $chartKeys[] = $key;
        $chartLabels[] = $monthNames[$monthIndex] . ' ' . substr($yearTh, 2);
        $chartDataMap[$key] = 0; // ตั้งค่าเริ่มต้นรายได้เป็น 0
    }

    $response = [
        'totalStudents' => 0,
        'currentMonthRevenue' => 0,
        'unpaidAmount' => 0,
        'totalCourses' => 0,
        'chartLabels' => $chartLabels,
        'chartData' => [],
        'unpaidList' => [],
        'exportData' => [],
        'allStudentsList' => []
    ];

    try {
        // 1. ดึงข้อมูลนักเรียนทั้งหมด
        $sqlUsers = "SELECT user_id, full_name, nickname, grade, phone FROM users WHERE role = 'student'";
        $resUsers = $conn->query($sqlUsers);
        if ($resUsers) {
            $response['totalStudents'] = $resUsers->num_rows;
            while ($row = $resUsers->fetch_assoc()) {
                $response['allStudentsList'][] = [
                    'studentId' => $row['user_id'],
                    'name' => $row['full_name'],
                    'nickname' => $row['nickname'],
                    'level' => $row['grade'],
                    'phone' => $row['phone']
                ];
            }
        }

        // 2. ดึงจำนวนคอร์สเรียน
        $resCourses = $conn->query("SELECT COUNT(*) as count FROM courses");
        if ($resCourses) {
            $response['totalCourses'] = (int)$resCourses->fetch_assoc()['count'];
        }

        // 3. ดึงข้อมูลการลงทะเบียนและคำนวณรายได้/ค้างชำระ
        $sqlEnroll = "
            SELECT 
                u.full_name, 
                c.name as course_name, 
                c.price, 
                e.approval_status, 
                e.payment_status, 
                COALESCE(e.approved_date, e.timestamp) as action_date 
            FROM enrollments e
            JOIN users u ON e.user_id = u.user_id
            JOIN courses c ON e.course_id = c.course_id
        ";
        $resEnroll = $conn->query($sqlEnroll);

        if ($resEnroll) {
            while ($row = $resEnroll->fetch_assoc()) {
                $status = $row['payment_status'];
                $price = (float)$row['price'];
                
                $actionDate = new DateTime($row['action_date']);
                $dateKey = $actionDate->format('Y-m');
                $dateStr = $actionDate->format('Y-m-d');

                // เช็คสถานะให้ตรงกับ Database ('approval_payment' หรือ 'paid' ถือว่าจ่ายแล้ว)
                $isPaid = ($status === 'paid' || $status === 'approval_payment');
                $isUnpaid = ($status === 'pending_payment');

                // ข้อมูลสำหรับ Export รายคอร์ส
                $exportStatus = $isPaid ? 'ชำระเงินแล้ว' : ($isUnpaid ? 'รอชำระเงิน' : 'อื่นๆ');
                $exportAmount = $isPaid ? $price : ($isUnpaid ? $price : 0);
                
                $response['exportData'][] = [
                    'courseName' => $row['course_name'],
                    'studentName' => $row['full_name'],
                    'amount' => $exportAmount,
                    'paymentStatus' => $exportStatus,
                    'payDate' => $isPaid ? $dateStr : '-'
                ];

                // ถ้ายอดชำระเงินแล้ว (คำนวณรายได้เดือนนี้ และกราฟ 6 เดือน)
                if ($isPaid) {
                    if ($dateKey === $currentMonthKey) {
                        $response['currentMonthRevenue'] += $price;
                    }
                    if (isset($chartDataMap[$dateKey])) {
                        $chartDataMap[$dateKey] += $price;
                    }
                }

                // ถ้ายอดค้างชำระ (เช่น อนุมัติการจองแล้ว แต่ยังไม่จ่ายเงิน)
                if ($row['approval_status'] === 'approved' && $isUnpaid) {
                    $response['unpaidAmount'] += $price;
                    $response['unpaidList'][] = [
                        'name' => $row['full_name'],
                        'course' => $row['course_name'],
                        'amount' => $price
                    ];
                }
            }
        }

        // จัดเรียงข้อมูลลงกราฟตามลำดับเดือน
        foreach ($chartKeys as $key) {
            $response['chartData'][] = $chartDataMap[$key];
        }

        echo json_encode($response);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sci Math Academy - Dashboard</title>
  
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

  <style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .app-layout { display: flex; min-height: 100vh; overflow-x: hidden; }
    .sidebar { width: 260px; transition: all 0.3s; flex-shrink: 0; z-index: 1000; }
    .nav-item { display: block; padding: 12px 20px; margin-bottom: 5px; border-radius: 8px; cursor: pointer; transition: 0.2s; }
    .nav-item:hover { background-color: rgba(255,255,255,0.1); }
    .main-content { flex-grow: 1; padding: 20px; transition: all 0.3s; width: 100%; }
    .dash-card { background: #fff; border-radius: 12px; }
    .icon-box { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
    .mobile-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.5); z-index: 999; }
    
    /* Responsive Sidebar */
    @media (max-width: 991.98px) {
      .sidebar { position: fixed; left: -260px; height: 100vh; }
      .sidebar.show { left: 0; }
      .mobile-overlay.show { display: block; }
    }
    @media (min-width: 992px) {
      .btn-toggle-menu { display: none !important; }
    }
  </style>
</head>
<body>

<div class="app-layout">
  <div class="mobile-overlay" id="mobileOverlay" onclick="toggleSidebar()"></div>
  <aside class="sidebar text-white shadow-sm d-flex flex-column" style="background-color: #2b4d7e;" id="adminNewsSidebar">
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
      <a href="dashboard.php" class="nav-item active text-dark fw-bold text-decoration-none" style="background: #f0f4f8; border-left: 4px solid #0d6efd;">
        <i class="bi bi-grid-1x2 text-primary me-2"></i> แดชบอร์ด
      </a>
      <a href="admin_courses.php" class="nav-item text-white text-opacity-75 text-decoration-none"><i class="bi bi-journal-bookmark me-2"></i> จัดการคอร์ส</a>
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
        <h4 class="fw-bold mb-0 text-dark">ภาพรวมระบบ (Dashboard)</h4>
      </div>
      <div class="fw-bold text-primary align-self-start align-self-md-auto bg-primary bg-opacity-10 px-3 py-2 rounded">
        <i class="bi bi-person-badge me-1"></i> ผู้ดูแลระบบ (<?php echo htmlspecialchars($_SESSION['sessionUser']); ?>)
      </div>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="dash-card d-flex align-items-center p-3 h-100 shadow-sm border-0">
          <div class="icon-box bg-primary bg-opacity-10 text-primary me-3"><i class="bi bi-people-fill"></i></div>
          <div><div class="text-muted small fw-semibold">นักเรียนทั้งหมด</div><h4 class="fw-bold mb-0" id="dashTotalStudents">-</h4></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="dash-card d-flex align-items-center p-3 h-100 shadow-sm border-0">
          <div class="icon-box bg-success bg-opacity-10 text-success me-3"><i class="bi bi-cash-stack"></i></div>
          <div><div class="text-muted small fw-semibold">รายได้เดือนนี้</div><h4 class="fw-bold mb-0 text-success" id="dashCurrentRevenue">-</h4></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="dash-card d-flex align-items-center p-3 h-100 shadow-sm border-0">
          <div class="icon-box bg-danger bg-opacity-10 text-danger me-3"><i class="bi bi-exclamation-circle-fill"></i></div>
          <div><div class="text-muted small fw-semibold">ยอดค้างชำระ</div><h4 class="fw-bold mb-0 text-danger" id="dashUnpaidAmount">-</h4></div>
        </div>
      </div>
      <div class="col-12 col-sm-6 col-lg-3">
        <div class="dash-card d-flex align-items-center p-3 h-100 shadow-sm border-0">
          <div class="icon-box bg-warning bg-opacity-10 text-warning me-3"><i class="bi bi-journal-check"></i></div>
          <div><div class="text-muted small fw-semibold">คอร์สที่เปิดสอน</div><h4 class="fw-bold mb-0" id="dashTotalCourses">-</h4></div>
        </div>
      </div>
    </div>

    <div class="row g-4 mb-4">
      <div class="col-12 col-lg-7">
        <div class="dash-card shadow-sm border-0 h-100 p-4">
          <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
             <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-fill me-2 text-primary"></i>สถิติรายได้ 6 เดือนล่าสุด</h6>
             <button class="btn btn-sm btn-success fw-bold" onclick="exportDashboardToExcel()">
               <i class="bi bi-file-earmark-excel me-1"></i> Export ข้อมูล
             </button>
          </div>
          <div style="position: relative; height:40vh; width:100%">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </div>
      
      <div class="col-12 col-lg-5">
        <div class="dash-card shadow-sm border-0 h-100 p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0 text-danger"><i class="bi bi-wallet2 me-2"></i>รายชื่อค้างจ่าย</h6>
            <span class="badge bg-danger rounded-pill px-3 py-2" id="dashUnpaidCountBadge">0 รายการ</span>
          </div>
          <div class="table-responsive border rounded" style="max-height: 40vh; overflow-y: auto;">
            <table class="table table-hover align-middle small mb-0">
              <thead class="table-light sticky-top">
                <tr><th>ชื่อ-สกุล</th><th>คอร์ส</th><th>ยอด(฿)</th></tr>
              </thead>
              <tbody id="dashUnpaidListBody">
                <tr><td colspan="3" class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span>กำลังโหลดข้อมูล...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
  let revenueChartInstance = null;
  let globalDashboardExportData = []; 
  let globalAllStudentsData = [];

  function toggleSidebar() {
    document.getElementById('adminNewsSidebar').classList.toggle('show');
    document.getElementById('mobileOverlay').classList.toggle('show');
  }

  window.onload = function() {
    loadDashboardData();
  };

  async function loadDashboardData() {
    try {
      const response = await fetch('dashboard.php?action=get_data');
      const data = await response.json();
      
      if(data.error) throw new Error(data.error);

      updateDashboardUI(data);
      renderChart(data.chartLabels, data.chartData);
      globalDashboardExportData = data.exportData;
      globalAllStudentsData = data.allStudentsList;

    } catch (error) {
      console.error("Failed to load dashboard data:", error);
    }
  }

  function updateDashboardUI(data) {
    document.getElementById('dashTotalStudents').innerText = data.totalStudents.toLocaleString();
    
    const revFormat = data.currentMonthRevenue >= 10000 
      ? (data.currentMonthRevenue / 1000).toFixed(1) + 'k' 
      : data.currentMonthRevenue.toLocaleString();
    document.getElementById('dashCurrentRevenue').innerText = '฿' + revFormat;

    const unpaidFormat = data.unpaidAmount >= 10000 
      ? (data.unpaidAmount / 1000).toFixed(1) + 'k' 
      : data.unpaidAmount.toLocaleString();
    document.getElementById('dashUnpaidAmount').innerText = '฿' + unpaidFormat;
    
    document.getElementById('dashTotalCourses').innerText = data.totalCourses.toLocaleString();
    document.getElementById('dashUnpaidCountBadge').innerText = data.unpaidList.length + ' รายการ';

    const unpaidTbody = document.getElementById('dashUnpaidListBody');
    unpaidTbody.innerHTML = '';
    
    if (data.unpaidList.length === 0) {
      unpaidTbody.innerHTML = '<tr><td colspan="3" class="text-center py-5"><i class="bi bi-check-circle fs-3 d-block mb-2"></i>ไม่มีรายการค้างชำระ</td></tr>';
    } else {
      data.unpaidList.forEach(item => {
        unpaidTbody.innerHTML += `
          <tr>
            <td class="text-truncate" style="max-width: 120px;" title="${item.name}">${item.name}</td>
            <td class="text-truncate" style="max-width: 120px;" title="${item.course}">${item.course}</td>
            <td class="text-danger fw-bold">${item.amount.toLocaleString()}</td>
          </tr>
        `;
      });
    }
  }

  function renderChart(labels, dataArray) {
    const ctx = document.getElementById('revenueChart');
    if(!ctx) return;
    
    if (revenueChartInstance) {
      revenueChartInstance.destroy();
    }
    
    revenueChartInstance = new Chart(ctx.getContext('2d'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'รายได้สุทธิ (บาท)',
          data: dataArray,
          backgroundColor: '#2b4d7e',
          borderRadius: 6,
          barPercentage: 0.6
        }]
      },
      options: { 
        responsive: true, 
        maintainAspectRatio: false,
        plugins: { 
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: function(context) {
                return ' ' + context.parsed.y.toLocaleString() + ' บาท';
              }
            }
          }
        }, 
        scales: { 
          y: { 
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                return value >= 1000 ? (value / 1000) + 'k' : value;
              }
            }
          } 
        } 
      }
    });
  }

  function exportDashboardToExcel() {
    const wb = XLSX.utils.book_new();

    // 1. ชีต "รายชื่อนักเรียนทั้งหมด"
    const studentHeaders = [
      ["รหัสนักเรียน", "ชื่อ-สกุล", "ชื่อเล่น", "ระดับชั้น", "เบอร์โทรศัพท์"]
    ];
    
    if (globalAllStudentsData && globalAllStudentsData.length > 0) {
      globalAllStudentsData.forEach(s => {
        studentHeaders.push([ s.studentId, s.name, s.nickname, s.level, s.phone ]);
      });
    } else {
      studentHeaders.push(["ไม่มีข้อมูล", "", "", "", ""]);
    }
    
    const wsStudents = XLSX.utils.aoa_to_sheet(studentHeaders);
    XLSX.utils.book_append_sheet(wb, wsStudents, "รายชื่อนักเรียนทั้งหมด");

    // 2. ชีตคอร์สแต่ละคอร์ส
    if (globalDashboardExportData && globalDashboardExportData.length > 0) {
      let coursesGroup = {};
      globalDashboardExportData.forEach(row => {
        if (!coursesGroup[row.courseName]) {
          coursesGroup[row.courseName] = [];
        }
        coursesGroup[row.courseName].push([
          row.studentName,
          row.amount,
          row.paymentStatus,
          row.payDate
        ]);
      });

      for (let course in coursesGroup) {
        let courseSheetData = [
          ["ชื่อนักเรียน", "ยอดเงิน (บาท)", "สถานะการชำระเงิน", "วันที่โอน"]
        ];
        courseSheetData = courseSheetData.concat(coursesGroup[course]);
        let wsCourse = XLSX.utils.aoa_to_sheet(courseSheetData);
        
        let safeSheetName = course.replace(/[\\/*?:\[\]]/g, '').substring(0, 31);
        if (!safeSheetName) safeSheetName = "Course";
        
        let finalSheetName = safeSheetName;
        let counter = 1;
        while (wb.SheetNames.includes(finalSheetName)) {
          finalSheetName = safeSheetName.substring(0, 27) + "_" + counter;
          counter++;
        }
        XLSX.utils.book_append_sheet(wb, wsCourse, finalSheetName);
      }
    } else {
      let wsEmpty = XLSX.utils.aoa_to_sheet([["ไม่มีข้อมูลการลงทะเบียน"]]);
      XLSX.utils.book_append_sheet(wb, wsEmpty, "ข้อมูลคอร์ส");
    }

    XLSX.writeFile(wb, "Sci_Math_Academy_Export.xlsx");
  }
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>