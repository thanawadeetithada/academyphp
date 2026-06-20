<?php
session_start();
require_once 'db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    header('Content-Type: application/json');
    
    $usernameInput = trim($_POST['username']);
    $passwordInput = $_POST['password'];

    try {
        // อัปเดตเป็น user_id
        $sql = "SELECT user_id, full_name, role, password_hash FROM users WHERE user_id = ? OR full_name = ? OR phone = ?";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("เกิดข้อผิดพลาดในการเตรียม SQL: " . $conn->error);
        }

        $stmt->bind_param("sss", $usernameInput, $usernameInput, $usernameInput);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user) {
            if (password_verify($passwordInput, $user['password_hash']) || $passwordInput === $user['password_hash']) {
                
                $_SESSION['sessionUser'] = $user['full_name'];
                $_SESSION['sessionRole'] = $user['role'];
                $_SESSION['userRowId'] = $user['user_id']; // อัปเดต
                
                echo json_encode([
                    'success' => true,
                    'user' => $user['full_name'],
                    'role' => $user['role'],
                    'userId' => $user['user_id'] // อัปเดต
                ]); 
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'รหัสผ่านไม่ถูกต้อง']); 
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบรหัสผู้ใช้งาน, ชื่อ หรือเบอร์โทรในระบบ']); 
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sci Math Academy - เข้าสู่ระบบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
    body {
        background-color: #f0f4f8;
        font-family: 'Prompt', sans-serif;
        color: #333;
        overflow-x: hidden;
    }

    .auth-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .card-container {
        width: 100%;
        max-width: 450px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        border-radius: 20px;
        border: none;
        background: #fff;
        overflow: hidden;
    }

    .btn-custom {
        background: linear-gradient(135deg, #2b4d7e, #3a68a8);
        color: white;
        border-radius: 10px;
        padding: 12px;
        font-weight: 500;
        border: none;
        transition: all 0.3s ease;
    }

    .btn-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(43, 77, 126, 0.3);
        color: white;
    }

    .form-control {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #e2e8f0;
        background-color: #f8faff;
    }

    .form-control:focus {
        border-color: #2b4d7e;
        box-shadow: 0 0 0 0.25rem rgba(43, 77, 126, 0.15);
        background-color: #fff;
    }

    @keyframes shake {

        0%,
        100% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(-5px);
        }

        75% {
            transform: translateX(5px);
        }
    }
    </style>
</head>

<body>

    <div class="auth-wrapper">
        <div class="card card-container">
            <div class="text-center pt-5 pb-3">
                <div style="display: flex; align-items: center; justify-content: center; margin: 0 auto 15px; ">
                    <img src="img/logo.png" style="width: 100px; height: 100px; object-fit: contain;">
                </div>
                <h3 class="mb-1 fw-bold text-dark">Sci Math Academy</h3>
                <span class="text-muted small">ระบบจัดการการเรียนการสอน</span>
            </div>

            <div class="card-body px-4 px-md-5 pb-5">
                <h5 class="mb-4 text-center fw-bold text-dark">เข้าสู่ระบบ</h5>

                <div id="loginError" class="alert alert-danger small py-3 mb-3 text-center d-none" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><span
                        id="loginErrorText">ข้อมูลไม่ถูกต้อง</span>
                </div>

                <form id="loginForm" onsubmit="handleLogin(event)">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-bold">รหัสประจำตัว / ชื่อ-สกุล / เบอร์โทร</label>
                        <input type="text" class="form-control" id="loginUsername"
                            placeholder="กรอกรหัส, ชื่อ หรือเบอร์โทร" required autocomplete="off">
                    </div>
                    <div class="mb-4 position-relative">
                        <label class="form-label text-muted small fw-bold">รหัสผ่าน</label>
                        <input type="password" class="form-control" id="loginPassword" placeholder="กรอกรหัสผ่าน"
                            required>
                        <i class="bi bi-eye text-muted position-absolute"
                            style="right: 15px; top: 45px; cursor: pointer;"
                            onclick="togglePasswordVisibility('loginPassword', this)"></i>
                    </div>
                    <button type="submit" class="btn btn-custom w-100 mb-4" id="btnLogin">เข้าสู่ระบบ</button>
                </form>

                <div class="text-center">
                    <span class="text-muted small">มีบัญชีแล้ว?</span>
                    <a href="register.php" class="text-decoration-none ms-1"
                        style="color: #2b4d7e; font-weight: 600;">ลงทะเบียนสมัครเรียน</a>
                </div>
            </div>
        </div>
    </div>

    <script>
    function togglePasswordVisibility(inputId, iconElement) {
        const input = document.getElementById(inputId);
        input.type = input.type === 'password' ? 'text' : 'password';
        iconElement.classList.toggle('bi-eye');
        iconElement.classList.toggle('bi-eye-slash');
    }

    async function handleLogin(e) {
        e.preventDefault();
        const btn = document.getElementById('btnLogin');
        const originalBtnText = btn.innerHTML;
        const user = document.getElementById('loginUsername').value.trim();
        const pass = document.getElementById('loginPassword').value;
        const errorDiv = document.getElementById('loginError');
        const errorText = document.getElementById('loginErrorText');

        errorDiv.classList.add('d-none');
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังตรวจสอบ...';
        btn.disabled = true;

        const formData = new URLSearchParams();
        formData.append('action', 'login');
        formData.append('username', user);
        formData.append('password', pass);

        try {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData
            });

            const result = await response.json();

            btn.innerHTML = originalBtnText;
            btn.disabled = false;

            if (result.success) {
                localStorage.setItem('sessionUser', result.user);
                localStorage.setItem('sessionRole', result.role);
                localStorage.setItem('userRowId', result.userId);

                // อัปเดตเงื่อนไขให้ตรงกับ DB ภาษาอังกฤษ
                if (result.role === 'admin') {
                    window.location.href = 'dashboard.php';
                } else {
                    window.location.href = 'main.php';
                }
            } else {
                errorText.innerText = result.message;
                errorDiv.classList.remove('d-none');
                errorDiv.style.animation = 'shake 0.4s';
                setTimeout(() => errorDiv.style.animation = '', 400);
            }
        } catch (error) {
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
            errorText.innerText = 'เกิดข้อผิดพลาดในการเชื่อมต่อกับเซิร์ฟเวอร์';
            errorDiv.classList.remove('d-none');
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>