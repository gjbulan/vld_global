

<?php
include '../config.php';

$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username']));
    $password = trim($_POST['password']);

    if ($username === "admin" && $password === "admin123") {
        $_SESSION['admin_id'] = 1;
        $_SESSION['admin_username'] = "admin";
        $success = true;
    } else {
        $error = "Invalid admin username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login - VLD Global</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --admin-deep: #111827;
            --admin-dark: #020617;
            --admin-panel: #0f172a;
            --admin-blue: #005477;
            --admin-gold: #C8AF55;
            --admin-red: #dc3545;
            --admin-muted: #94a3b8;
            --admin-light: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                radial-gradient(circle at top left, rgba(200,175,85,0.20), transparent 34%),
                radial-gradient(circle at bottom right, rgba(0,84,119,0.30), transparent 38%),
                linear-gradient(135deg, var(--admin-dark), var(--admin-deep));
            color: var(--admin-light);
        }

        .admin-login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
            position: relative;
            overflow: hidden;
        }

        .admin-login-page::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.035) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.035) 1px, transparent 1px);
            background-size: 42px 42px;
            mask-image: linear-gradient(to bottom, rgba(0,0,0,0.75), transparent);
        }

        .admin-login-card {
            width: 100%;
            max-width: 980px;
            min-height: 590px;
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            background: rgba(15,23,42,0.88);
            border: 1px solid rgba(200,175,85,0.20);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 35px 90px rgba(0,0,0,0.55);
            position: relative;
            z-index: 2;
        }

        .admin-info-panel {
            padding: 48px;
            background:
                linear-gradient(135deg, rgba(2,6,23,0.96), rgba(15,23,42,0.94)),
                url("https://images.unsplash.com/photo-1451187580459-43490279c0fa?auto=format&fit=crop&w=1200&q=80");
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            border-right: 1px solid rgba(255,255,255,0.08);
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(220,53,69,0.14);
            color: #fecaca;
            border: 1px solid rgba(220,53,69,0.35);
            padding: 9px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            width: fit-content;
        }

        .admin-info-panel h1 {
            margin: 28px 0 12px;
            font-size: 43px;
            line-height: 1.08;
            font-weight: 900;
            color: #ffffff;
        }

        .admin-info-panel h1 span {
            color: var(--admin-gold);
        }

        .admin-info-panel p {
            color: rgba(229,231,235,0.82);
            line-height: 1.75;
            font-size: 16px;
            max-width: 420px;
        }

        .security-list {
            display: grid;
            gap: 12px;
            margin-top: 28px;
        }

        .security-list div {
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.09);
            border-radius: 16px;
            padding: 13px 15px;
            color: rgba(255,255,255,0.86);
            font-weight: 700;
            font-size: 14px;
        }

        .admin-footer-text {
            color: rgba(148,163,184,0.9);
            font-size: 13px;
            letter-spacing: 1.7px;
            text-transform: uppercase;
        }

        .admin-form-panel {
            padding: 52px;
            display: flex;
            align-items: center;
            background: rgba(15,23,42,0.98);
        }

        .admin-form-inner {
            width: 100%;
            max-width: 430px;
            margin: 0 auto;
        }

        .admin-logo-wrap {
            text-align: center;
            margin-bottom: 22px;
        }

        .admin-logo-wrap img {
            width: 160px;
            max-width: 100%;
            filter: drop-shadow(0 10px 25px rgba(0,0,0,0.35));
        }

        .admin-form-inner h3 {
            text-align: center;
            color: #ffffff;
            font-size: 30px;
            font-weight: 900;
            margin-bottom: 8px;
        }

        .admin-subtitle {
            text-align: center;
            color: var(--admin-muted);
            margin-bottom: 30px;
        }

        .admin-label {
            color: #cbd5e1;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .admin-input {
            height: 54px;
            border-radius: 15px;
            background: #020617;
            border: 1px solid rgba(148,163,184,0.25);
            color: #ffffff;
            padding: 12px 16px;
        }

        .admin-input:focus {
            background: #020617;
            color: #ffffff;
            border-color: var(--admin-gold);
            box-shadow: 0 0 0 0.22rem rgba(200,175,85,0.18);
        }

        .admin-input::placeholder {
            color: #64748b;
        }

        .admin-btn {
            height: 56px;
            border: none;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--admin-gold), #a88c32);
            color: #020617;
            font-weight: 900;
            letter-spacing: 0.5px;
            margin-top: 8px;
            box-shadow: 0 16px 30px rgba(200,175,85,0.20);
        }

        .admin-btn:hover {
            background: linear-gradient(135deg, #f0d77a, var(--admin-gold));
            color: #020617;
        }

        .admin-links {
            text-align: center;
            margin-top: 26px;
        }

        .admin-links a {
            color: #93c5fd;
            text-decoration: none;
            font-weight: 800;
        }

        .admin-links a:hover {
            color: var(--admin-gold);
        }

        .alert {
            border-radius: 15px;
            font-weight: 700;
        }

        .alert-danger {
            background: rgba(220,53,69,0.16);
            border-color: rgba(220,53,69,0.35);
            color: #fecaca;
        }

        .alert-success {
            background: rgba(34,197,94,0.15);
            border-color: rgba(34,197,94,0.35);
            color: #bbf7d0;
        }

        @media (max-width: 900px) {
            .admin-login-card {
                grid-template-columns: 1fr;
                max-width: 540px;
                min-height: auto;
            }

            .admin-info-panel {
                display: none;
            }

            .admin-form-panel {
                padding: 42px 26px;
            }
        }

        @media (max-width: 480px) {
            .admin-login-page {
                padding: 18px;
            }

            .admin-login-card {
                border-radius: 24px;
            }

            .admin-form-panel {
                padding: 34px 20px;
            }

            .admin-form-inner h3 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

<div class="admin-login-page">
    <div class="admin-login-card">

        <div class="admin-info-panel">
            <div>
                <div class="admin-badge">Admin Access Only</div>

                <h1>Control Center<br><span>Management Portal</span></h1>

                <p>
                    Secure back-office access for managing members, package codes,
                    product codes, payouts, reports, and compensation records.
                </p>

                <div class="security-list">
                    <div>Member & package management</div>
                    <div>Bonus ledger monitoring</div>
                    <div>Code generation control</div>
                    <div>Payout and report oversight</div>
                </div>
            </div>

            <div class="admin-footer-text">
                VLD Global Admin Console
            </div>
        </div>

        <div class="admin-form-panel">
            <div class="admin-form-inner">

                <div class="admin-logo-wrap">
                    <img src="../assets/logo.png" alt="VLD Global">
                </div>

                <h3>Admin Login</h3>
                <p class="admin-subtitle">
                    Authorized personnel only
                </p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success text-center">
                        Admin login successful. Redirecting...
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" autocomplete="off">
                    <div class="mb-3">
                        <label class="admin-label">Admin Username</label>
                        <input 
                            type="text" 
                            name="username" 
                            class="form-control admin-input" 
                            placeholder="Enter admin username"
                            required 
                            autofocus
                        >
                    </div>

                    <div class="mb-3">
                        <label class="admin-label">Admin Password</label>
                        <input 
                            type="password" 
                            name="password" 
                            class="form-control admin-input" 
                            placeholder="Enter admin password"
                            required
                        >
                    </div>

                    <button type="submit" class="btn admin-btn w-100">
                        Enter Admin Panel
                    </button>
                </form>

                <div class="admin-links">
                    <a href="../login.php">Back to Member Login</a>
                </div>

            </div>
        </div>

    </div>
</div>

<?php if ($success): ?>
<script>
    setTimeout(function () {
        window.location.replace("/vld_global/admin/index.php");
    }, 900);
</script>
<?php endif; ?>

</body>
</html>