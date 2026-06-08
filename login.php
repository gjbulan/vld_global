

<?php
include 'functions.php';

$success = false;
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = strtolower(trim($_POST['username']));
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM members WHERE username=? AND status='active'");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    if ($member && password_verify($password, $member['password'])) {

        $_SESSION['member_id'] = $member['id'];
        $_SESSION['username'] = $member['username'];
        $_SESSION['member_code'] = $member['member_code'];

        $success = true;

    } else {
        $error = "Invalid username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Member Login - VLD Global</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --ocean: #005477;
            --ocean-light: #007ba7;
            --deep: #032935;
            --gold: #C8AF55;
            --gold-dark: #9e8431;
            --white-glass: rgba(255, 255, 255, 0.92);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                linear-gradient(135deg, rgba(0, 84, 119, 0.78), rgba(3, 41, 53, 0.82)),
                url("https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1800&q=80");
            background-size: cover;
            background-position: center;
        }

        .login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }

        .login-page::before {
            content: "";
            position: absolute;
            width: 550px;
            height: 550px;
            background: rgba(200, 175, 85, 0.22);
            border-radius: 50%;
            top: -180px;
            right: -150px;
            filter: blur(8px);
        }

        .login-page::after {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(0, 84, 119, 0.35);
            border-radius: 50%;
            bottom: -200px;
            left: -130px;
            filter: blur(10px);
        }

        .login-box {
            width: 100%;
            max-width: 1180px;
            min-height: 680px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 36px;
            overflow: hidden;
            box-shadow: 0 35px 90px rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(12px);
            position: relative;
            z-index: 2;
        }

        .brand-panel {
            padding: 60px;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(135deg, rgba(0, 84, 119, 0.92), rgba(3, 41, 53, 0.94)),
                url("https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80");
            background-size: cover;
            background-position: center;
        }

        .brand-panel h1 {
            font-size: 54px;
            line-height: 1.05;
            font-weight: 900;
            margin: 0;
            color: var(--gold);
            text-shadow: 0 5px 20px rgba(0,0,0,0.35);
        }

        .brand-panel h2 {
            font-size: 28px;
            margin-top: 15px;
            font-weight: 700;
        }

        .brand-panel p {
            font-size: 18px;
            line-height: 1.8;
            max-width: 560px;
            color: rgba(255,255,255,0.88);
            margin-top: 22px;
        }

        .tag-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 28px;
        }

        .tag-list span {
            padding: 11px 18px;
            border-radius: 50px;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(200,175,85,0.45);
            font-weight: 700;
            color: #fff;
        }

        .brand-footer {
            font-size: 14px;
            color: rgba(255,255,255,0.78);
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .form-panel {
            background: var(--white-glass);
            padding: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .form-inner {
            width: 100%;
            max-width: 430px;
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-wrap img {
            width: 250px;
            max-width: 100%;
        }

        .form-inner h3 {
            text-align: center;
            color: var(--ocean);
            font-size: 30px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .form-inner .subtitle {
            text-align: center;
            color: #667780;
            margin-bottom: 32px;
        }

        .form-label {
            font-weight: 700;
            color: #263238;
            margin-bottom: 8px;
        }

        .form-control {
            height: 54px;
            border-radius: 16px;
            border: 1px solid #d7e1e6;
            padding: 12px 16px;
            font-size: 15px;
        }

        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 0.22rem rgba(200,175,85,0.22);
        }

        .login-btn {
            height: 56px;
            border-radius: 18px;
            border: none;
            background: linear-gradient(135deg, var(--ocean), var(--ocean-light));
            color: #fff;
            font-weight: 900;
            letter-spacing: 0.5px;
            margin-top: 8px;
            box-shadow: 0 14px 26px rgba(0,84,119,0.28);
        }

        .login-btn:hover {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--deep);
        }

        .login-links {
            text-align: center;
            margin-top: 26px;
        }

        .login-links a {
            color: var(--ocean);
            text-decoration: none;
            font-weight: 800;
        }

        .login-links a:hover {
            color: var(--gold-dark);
        }

        .alert {
            border-radius: 16px;
            font-weight: 700;
        }

        .mobile-brand {
            display: none;
            text-align: center;
            color: var(--ocean);
            font-weight: 900;
            margin-bottom: 15px;
        }

        @media (max-width: 992px) {
            .login-box {
                grid-template-columns: 1fr;
                max-width: 560px;
                min-height: auto;
            }

            .brand-panel {
                display: none;
            }

            .form-panel {
                padding: 38px 26px;
            }

            .mobile-brand {
                display: block;
            }

            .logo-wrap img {
                width: 210px;
            }
        }

        @media (max-width: 480px) {
            .login-page {
                padding: 18px;
            }

            .login-box {
                border-radius: 26px;
            }

            .form-panel {
                padding: 30px 20px;
            }

            .form-inner h3 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<div class="login-page">
    <div class="login-box">

        <div class="brand-panel">
            <div>
                <h1>Travel. Build. Live.</h1>
                <h2>VLD Global Compensation System</h2>
                <p>
                    Build your legacy through a global community, premium rewards,
                    and a lifestyle designed around travel, freedom, and growth.
                </p>

                <div class="tag-list">
                    <span>Ocean Lifestyle</span>
                    <span>Global Network</span>
                    <span>Premium Rewards</span>
                </div>
            </div>

            <div class="brand-footer">
                Vision • Legacy • Dominance
            </div>
        </div>

        <div class="form-panel">
            <div class="form-inner">

                <div class="mobile-brand">
                    VLD Global
                </div>

                <div class="logo-wrap">
                    <img src="assets/logo2.png" alt="VLD Global">
                </div>

                <h3>Member Login</h3>
                <p class="subtitle">Welcome back to your global journey</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success text-center">
                        Login successful. Redirecting...
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-control" 
                            placeholder="Enter your username"
                            required 
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn login-btn w-100">
                        Login
                    </button>
                </form>

                <div class="login-links">
                    <a href="register.php">Create Member Account</a>
                    <span class="mx-2 text-muted">|</span>
                    <a href="admin/login.php">Admin Login</a>
                </div>

            </div>
        </div>

    </div>
</div>

<?php if ($success): ?>
<script>
    setTimeout(function () {
        window.location.replace("/vld_global/index.php");
    }, 900);
</script>
<?php endif; ?>

</body>
</html>