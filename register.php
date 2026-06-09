<?php
include 'functions.php';

function normalizeContactNumber($contact_no) {
    return preg_replace('/[\s-]+/', '', trim($contact_no));
}

function isValidUsernameFormat($username) {
    return preg_match('/^[a-z0-9]{4,20}$/', $username);
}

function isValidContactNumber($contact_no) {
    $normalized = normalizeContactNumber($contact_no);
    return preg_match('/^\+?[0-9]{10,20}$/', $normalized);
}

function usernameExists($conn, $username) {
    $stmt = $conn->prepare("SELECT id FROM members WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function emailExists($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM members WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function contactExists($conn, $contact_no) {
    $stmt = $conn->prepare("SELECT id FROM members WHERE contact_no=? LIMIT 1");
    $stmt->bind_param("s", $contact_no);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function getSponsorDetails($conn, $username) {
    $stmt = $conn->prepare("SELECT id, username, member_code, full_name FROM members WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getPackageCodeDetails($conn, $package_code) {
    $stmt = $conn->prepare("
        SELECT pc.id, pc.code, pc.status, p.name AS package_name, p.price
        FROM package_codes pc
        JOIN packages p ON pc.package_id = p.id
        WHERE pc.code=? AND pc.status='unused'
        LIMIT 1
    ");
    $stmt->bind_param("s", $package_code);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function sendJsonResponse($payload) {
    header("Content-Type: application/json");
    echo json_encode($payload);
    exit;
}

if (isset($_GET['ajax'])) {
    $action = trim($_GET['ajax']);
    $value = trim($_GET['value'] ?? '');

    if ($action === "username") {
        $username = $value;

        if ($username === "") {
            sendJsonResponse(["valid" => false, "available" => false, "message" => "Username is required."]);
        }

        if (!isValidUsernameFormat($username)) {
            sendJsonResponse([
                "valid" => false,
                "available" => false,
                "message" => "Use 4-20 lowercase letters and numbers only."
            ]);
        }

        if (usernameExists($conn, $username)) {
            sendJsonResponse(["valid" => true, "available" => false, "message" => "Username is already taken."]);
        }

        sendJsonResponse(["valid" => true, "available" => true, "message" => "Username is available."]);
    }

    if ($action === "email") {
        $email = $value;

        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendJsonResponse(["valid" => false, "available" => false, "message" => "Enter a valid email address."]);
        }

        if (emailExists($conn, $email)) {
            sendJsonResponse(["valid" => true, "available" => false, "message" => "Email address is already registered."]);
        }

        sendJsonResponse(["valid" => true, "available" => true, "message" => "Email address is available."]);
    }

    if ($action === "contact") {
        $contact_no = normalizeContactNumber($value);

        if (!isValidContactNumber($contact_no)) {
            sendJsonResponse([
                "valid" => false,
                "available" => false,
                "normalized" => $contact_no,
                "message" => "Valid contact number is required."
            ]);
        }

        if (contactExists($conn, $contact_no)) {
            sendJsonResponse([
                "valid" => true,
                "available" => false,
                "normalized" => $contact_no,
                "message" => "Contact number is already registered."
            ]);
        }

        sendJsonResponse([
            "valid" => true,
            "available" => true,
            "normalized" => $contact_no,
            "message" => "Contact number is available."
        ]);
    }

    if ($action === "sponsor") {
        $sponsor_username = strtolower($value);

        if ($sponsor_username === "" || !preg_match('/^[a-z0-9]{4,20}$/', $sponsor_username)) {
            sendJsonResponse(["found" => false, "message" => "Sponsor Not Found"]);
        }

        $sponsor = getSponsorDetails($conn, $sponsor_username);

        if (!$sponsor) {
            sendJsonResponse(["found" => false, "message" => "Sponsor Not Found"]);
        }

        sendJsonResponse([
            "found" => true,
            "message" => "Sponsor Found",
            "sponsor" => [
                "username" => $sponsor['username'],
                "full_name" => $sponsor['full_name'],
                "member_code" => $sponsor['member_code']
            ]
        ]);
    }

    if ($action === "package") {
        $package_code = strtoupper($value);

        if ($package_code === "") {
            sendJsonResponse(["found" => false, "message" => "Enter a package code."]);
        }

        $package = getPackageCodeDetails($conn, $package_code);

        if (!$package) {
            sendJsonResponse(["found" => false, "message" => "Invalid or already used package activation code."]);
        }

        sendJsonResponse([
            "found" => true,
            "message" => "Package code is valid.",
            "package" => [
                "name" => $package['package_name'],
                "price" => number_format((float)$package['price'], 2)
            ]
        ]);
    }

    sendJsonResponse(["error" => "Invalid lookup request."]);
}

$error = "";
$success = "";

$ref_username = isset($_GET['ref']) ? strtolower(trim($_GET['ref'])) : "";
$username = "";
$full_name = "";
$email = "";
$contact_no = "";
$sponsor_username = "";
$package_code = "";
$readonly = "";
$ref_member = null;

if ($ref_username !== "") {
    $ref_member = getSponsorDetails($conn, $ref_username);

    if ($ref_member) {
        $sponsor_username = $ref_member['username'];
        $readonly = "readonly";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $contact_no = normalizeContactNumber($_POST['contact_no'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $sponsor_username = strtolower(trim($_POST['sponsor_username'] ?? ''));
    $package_code = strtoupper(trim($_POST['package_code'] ?? ''));
    $disclaimer_accepted = isset($_POST['disclaimer_accepted']) && $_POST['disclaimer_accepted'] === '1';

    if ($full_name === "") {
        $error = "Full name is required.";
    } elseif (!isValidUsernameFormat($username)) {
        $error = "Username must be 4 to 20 characters and use lowercase letters and numbers only.";
    } elseif (usernameExists($conn, $username)) {
        $error = "Username is already taken. Please choose another one.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (emailExists($conn, $email)) {
        $error = "Email address is already registered.";
    } elseif (!isValidContactNumber($contact_no)) {
        $error = "Valid contact number is required.";
    } elseif (contactExists($conn, $contact_no)) {
        $error = "Contact number is already registered.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif ($sponsor_username === "" || !preg_match('/^[a-z0-9]{4,20}$/', $sponsor_username)) {
        $error = "Valid sponsor username is required.";
    } elseif ($username === $sponsor_username) {
        $error = "Self referral is not allowed.";
    } elseif (!$disclaimer_accepted) {
        $error = "Please acknowledge the VLD Global Disclaimer and Risk Disclosure.";
    } else {
        $sponsor = getMemberByUsername($conn, $sponsor_username);

        if (!$sponsor) {
            $error = "Sponsor username does not exist.";
        } else {
            try {
                ensureChairmanBonusLedgerTable($conn);
                $conn->begin_transaction();

                $stmt = $conn->prepare("
                    SELECT pc.*, p.name AS package_name, p.direct_bonus
                    FROM package_codes pc
                    JOIN packages p ON pc.package_id = p.id
                    WHERE pc.code=? AND pc.status='unused'
                    LIMIT 1
                    FOR UPDATE
                ");
                $stmt->bind_param("s", $package_code);
                $stmt->execute();
                $code_data = $stmt->get_result()->fetch_assoc();

                if (!$code_data) {
                    throw new Exception("Invalid or already used package activation code.");
                }

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $member_code = generateMemberCode($conn);
                $sponsor_id = (int)$sponsor['id'];
                $package_id = (int)$code_data['package_id'];

                $stmt = $conn->prepare("
                    INSERT INTO members
                    (username, member_code, full_name, email, contact_no, password, sponsor_id, package_id, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");

                $stmt->bind_param(
                    "ssssssii",
                    $username,
                    $member_code,
                    $full_name,
                    $email,
                    $contact_no,
                    $hashed_password,
                    $sponsor_id,
                    $package_id
                );

                if (!$stmt->execute()) {
                    throw new Exception("Registration failed. Please try again.");
                }

                $new_member_id = $conn->insert_id;

                $stmt = $conn->prepare("
                    UPDATE package_codes
                    SET status='used', used_by_member_id=?, used_at=NOW()
                    WHERE id=? AND status='unused'
                ");
                $stmt->bind_param("ii", $new_member_id, $code_data['id']);

                if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                    throw new Exception("Package activation code could not be reserved.");
                }

                addBonus(
                    $conn,
                    $sponsor_id,
                    $code_data['direct_bonus'],
                    "direct_referral",
                    "Direct referral bonus from " . $username
                );

                processGenerationBonuses($conn, $new_member_id, $sponsor_id, $package_id);

                processCashbackAndAdvancement($conn, $sponsor_id);

                $conn->commit();
                $success = "Registration successful. You may now login.";
            } catch (Throwable $e) {
                $conn->rollback();
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Member Registration - VLD Global</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        :root {
            --ocean: #005477;
            --ocean-light: #007ba7;
            --deep: #032935;
            --gold: #C8AF55;
            --gold-dark: #9e8431;
            --white-glass: rgba(255, 255, 255, 0.94);
            --success: #198754;
            --danger: #dc3545;
            --warning: #d69e2e;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, sans-serif;
            background:
                linear-gradient(135deg, rgba(0, 84, 119, 0.72), rgba(3, 41, 53, 0.80)),
                url("https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1800&q=80");
            background-size: cover;
            background-position: center;
        }

        .register-page {
            min-height: 100vh;
            padding: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
        }

        .register-page::before {
            content: "";
            position: absolute;
            width: 560px;
            height: 560px;
            background: rgba(200, 175, 85, 0.22);
            border-radius: 50%;
            top: -180px;
            right: -160px;
            filter: blur(10px);
        }

        .register-page::after {
            content: "";
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(0, 84, 119, 0.35);
            border-radius: 50%;
            bottom: -210px;
            left: -150px;
            filter: blur(12px);
        }

        .register-box {
            width: 100%;
            max-width: 1240px;
            display: grid;
            grid-template-columns: 0.85fr 1.15fr;
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
            padding: 54px;
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background:
                linear-gradient(135deg, rgba(0, 84, 119, 0.93), rgba(3, 41, 53, 0.95)),
                url("https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=80");
            background-size: cover;
            background-position: center;
        }

        .brand-panel h1 {
            font-size: 48px;
            line-height: 1.05;
            font-weight: 900;
            margin: 0;
            color: var(--gold);
            text-shadow: 0 5px 20px rgba(0,0,0,0.35);
        }

        .brand-panel h2 {
            font-size: 25px;
            margin-top: 15px;
            font-weight: 700;
        }

        .brand-panel p {
            font-size: 17px;
            line-height: 1.8;
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
            padding: 42px 46px;
        }

        .logo-wrap {
            text-align: center;
            margin-bottom: 18px;
        }

        .logo-wrap img {
            width: 190px;
            max-width: 100%;
        }

        .form-title {
            text-align: center;
            margin-bottom: 24px;
        }

        .form-title h3 {
            color: var(--ocean);
            font-size: 30px;
            font-weight: 900;
            margin-bottom: 6px;
        }

        .form-title p {
            color: #667780;
            margin: 0;
        }

        .step-card {
            background: rgba(255, 255, 255, 0.68);
            border: 1px solid rgba(0, 84, 119, 0.10);
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 18px;
        }

        .step-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .step-number {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ocean), var(--ocean-light));
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            flex: 0 0 auto;
        }

        .step-heading h4 {
            margin: 0;
            color: var(--deep);
            font-size: 17px;
            font-weight: 900;
        }

        .form-floating > .form-control {
            min-height: 56px;
            border-radius: 15px;
            border: 1px solid #d7e1e6;
            font-size: 15px;
        }

        .form-floating > label {
            color: #63727a;
            font-weight: 700;
        }

        .form-control:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 0.22rem rgba(200,175,85,0.22);
        }

        .form-control[readonly] {
            background: rgba(0, 84, 119, 0.08);
            color: var(--ocean);
            font-weight: 800;
        }

        .field-feedback {
            min-height: 20px;
            margin-top: 7px;
            font-size: 13px;
            font-weight: 800;
        }

        .field-feedback.is-success {
            color: var(--success);
        }

        .field-feedback.is-danger {
            color: var(--danger);
        }

        .lookup-panel {
            display: none;
            border-radius: 16px;
            padding: 14px 15px;
            margin-top: 10px;
            font-size: 14px;
            border: 1px solid rgba(0, 84, 119, 0.14);
            background: rgba(0, 84, 119, 0.07);
        }

        .lookup-panel.is-visible {
            display: block;
        }

        .lookup-panel.is-success {
            border-color: rgba(25, 135, 84, 0.28);
            background: rgba(25, 135, 84, 0.08);
            color: #0f5132;
        }

        .lookup-panel.is-danger {
            border-color: rgba(220, 53, 69, 0.28);
            background: rgba(220, 53, 69, 0.08);
            color: #842029;
        }

        .lookup-title {
            font-weight: 900;
            margin-bottom: 6px;
        }

        .lookup-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            padding-top: 7px;
            margin-top: 7px;
        }

        .lookup-row span:first-child {
            color: #56636a;
            font-weight: 800;
        }

        .lookup-row span:last-child {
            color: var(--deep);
            font-weight: 900;
            text-align: right;
        }

        .password-meter {
            display: none;
            margin-top: 8px;
        }

        .password-meter.is-visible {
            display: block;
        }

        .meter-track {
            height: 8px;
            background: #dde7eb;
            border-radius: 999px;
            overflow: hidden;
        }

        .meter-fill {
            height: 100%;
            width: 0;
            border-radius: 999px;
            transition: width 0.2s ease, background 0.2s ease;
        }

        .meter-label {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            font-weight: 900;
        }

        .password-meter.weak .meter-fill {
            width: 34%;
            background: var(--danger);
        }

        .password-meter.medium .meter-fill {
            width: 67%;
            background: var(--warning);
        }

        .password-meter.strong .meter-fill {
            width: 100%;
            background: var(--success);
        }

        .password-meter.weak .meter-label {
            color: var(--danger);
        }

        .password-meter.medium .meter-label {
            color: var(--warning);
        }

        .password-meter.strong .meter-label {
            color: var(--success);
        }

        .disclaimer-card {
            background: linear-gradient(135deg, rgba(255, 248, 219, 0.98), rgba(255, 255, 255, 0.95));
            border: 1px solid rgba(200, 175, 85, 0.58);
            border-radius: 20px;
            box-shadow: 0 14px 32px rgba(158, 132, 49, 0.14);
            padding: 18px;
            margin-bottom: 22px;
        }

        .disclaimer-header {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--deep);
            margin-bottom: 14px;
        }

        .warning-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--gold), #f2d879);
            color: var(--deep);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 900;
            flex: 0 0 auto;
            box-shadow: 0 8px 18px rgba(200, 175, 85, 0.25);
        }

        .disclaimer-header h4 {
            margin: 0;
            color: var(--ocean);
            font-size: 19px;
            font-weight: 900;
        }

        .disclaimer-box {
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid rgba(200, 175, 85, 0.32);
            border-radius: 16px;
            padding: 15px;
            color: #3f4b50;
            font-size: 14px;
            line-height: 1.65;
        }

        .disclaimer-box strong {
            display: block;
            color: var(--deep);
            font-size: 15px;
            margin-bottom: 8px;
        }

        .disclaimer-box ul {
            margin: 8px 0 0;
            padding-left: 20px;
        }

        .disclaimer-check {
            background: rgba(200, 175, 85, 0.12);
            border-radius: 14px;
            padding: 13px 14px;
            margin-top: 14px;
        }

        .disclaimer-check .form-check-input {
            border-color: var(--gold-dark);
        }

        .disclaimer-check .form-check-input:checked {
            background-color: var(--gold-dark);
            border-color: var(--gold-dark);
        }

        .disclaimer-check .form-check-label {
            color: var(--deep);
            font-weight: 800;
            line-height: 1.45;
        }

        .register-btn {
            height: 56px;
            border-radius: 18px;
            border: none;
            background: linear-gradient(135deg, var(--ocean), var(--ocean-light));
            color: #fff;
            font-weight: 900;
            letter-spacing: 0.5px;
            margin-top: 2px;
            box-shadow: 0 14px 26px rgba(0,84,119,0.28);
        }

        .register-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--deep);
        }

        .register-btn:disabled {
            opacity: 0.55;
            cursor: not-allowed;
        }

        .login-links {
            text-align: center;
            margin-top: 24px;
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

        .success-animation {
            width: 74px;
            height: 74px;
            border-radius: 50%;
            background: rgba(25, 135, 84, 0.12);
            border: 2px solid rgba(25, 135, 84, 0.35);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--success);
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 10px;
            animation: successPop 0.55s ease both;
        }

        @keyframes successPop {
            0% {
                transform: scale(0.65);
                opacity: 0;
            }
            70% {
                transform: scale(1.08);
                opacity: 1;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .mobile-brand {
            display: none;
            text-align: center;
            color: var(--ocean);
            font-weight: 900;
            margin-bottom: 15px;
        }

        @media (max-width: 992px) {
            .register-box {
                grid-template-columns: 1fr;
                max-width: 700px;
            }

            .brand-panel {
                display: none;
            }

            .mobile-brand {
                display: block;
            }

            .form-panel {
                padding: 36px 26px;
            }

            .disclaimer-box {
                max-height: 240px;
                overflow-y: auto;
            }
        }

        @media (max-width: 576px) {
            .register-page {
                padding: 18px;
            }

            .register-box {
                border-radius: 26px;
            }

            .form-panel {
                padding: 30px 20px;
            }

            .form-title h3 {
                font-size: 25px;
            }

            .logo-wrap img {
                width: 170px;
            }

            .step-card {
                padding: 15px;
            }

            .lookup-row {
                display: block;
            }

            .lookup-row span:last-child {
                display: block;
                text-align: left;
                margin-top: 2px;
            }
        }
    </style>
</head>

<body>

<div class="register-page">
    <div class="register-box">

        <div class="brand-panel">
            <div>
                <h1>Start Your Global Journey</h1>
                <h2>Build your network. Grow your legacy.</h2>
                <p>
                    Join VLD Global and begin your path toward community growth,
                    premium rewards, travel experiences, and long-term legacy building.
                </p>

                <div class="tag-list">
                    <span>Vision</span>
                    <span>Legacy</span>
                    <span>Dominance</span>
                    <span>Global Community</span>
                </div>
            </div>

            <div class="brand-footer">
                Travel &#8226; Build &#8226; Live
            </div>
        </div>

        <div class="form-panel">
            <div class="mobile-brand">
                VLD Global
            </div>

            <div class="logo-wrap">
                <img src="assets/logo.png" alt="VLD Global">
            </div>

            <div class="form-title">
                <h3>Member Registration</h3>
                <p>Create your account and activate your package</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success text-center">
                    <div class="success-animation">&#10003;</div>
                    <div><?php echo htmlspecialchars($success); ?></div>
                    <small>Redirecting to login...</small>
                </div>
            <?php endif; ?>

            <form method="POST" action="" autocomplete="off" id="registrationForm">
                <div class="step-card">
                    <div class="step-heading">
                        <span class="step-number">1</span>
                        <h4>Account Details</h4>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="text"
                                    name="full_name"
                                    id="fullName"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($full_name); ?>"
                                    placeholder="Full Name"
                                    required
                                >
                                <label for="fullName">Full Name</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="text"
                                    name="username"
                                    id="username"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($username); ?>"
                                    placeholder="Username"
                                    minlength="4"
                                    maxlength="20"
                                    pattern="[a-z0-9]{4,20}"
                                    required
                                >
                                <label for="username">Username</label>
                            </div>
                            <div class="field-feedback" id="usernameFeedback"></div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="email"
                                    name="email"
                                    id="email"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($email); ?>"
                                    placeholder="Email Address"
                                    required
                                >
                                <label for="email">Email Address</label>
                            </div>
                            <div class="field-feedback" id="emailFeedback"></div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="text"
                                    name="contact_no"
                                    id="contactNo"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($contact_no); ?>"
                                    placeholder="Contact Number"
                                    maxlength="20"
                                    pattern="[0-9+\-\s]{10,20}"
                                    required
                                >
                                <label for="contactNo">Contact Number</label>
                            </div>
                            <div class="field-feedback" id="contactFeedback"></div>
                        </div>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-heading">
                        <span class="step-number">2</span>
                        <h4>Sponsor &amp; Package</h4>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="text"
                                    name="sponsor_username"
                                    id="sponsorUsername"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($sponsor_username); ?>"
                                    placeholder="Sponsor Username"
                                    minlength="4"
                                    maxlength="20"
                                    <?php echo $readonly; ?>
                                    required
                                >
                                <label for="sponsorUsername">Sponsor Username</label>
                            </div>

                            <div
                                class="lookup-panel <?php echo $ref_member ? 'is-visible is-success' : ''; ?>"
                                id="sponsorPreview"
                            >
                                <?php if ($ref_member): ?>
                                    <div class="lookup-title">&#10004; Sponsor Found</div>
                                    <div class="lookup-row">
                                        <span>Full Name</span>
                                        <span><?php echo htmlspecialchars($ref_member['full_name']); ?></span>
                                    </div>
                                    <div class="lookup-row">
                                        <span>Member Code</span>
                                        <span><?php echo htmlspecialchars($ref_member['member_code']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="text"
                                    name="package_code"
                                    id="packageCode"
                                    class="form-control"
                                    value="<?php echo htmlspecialchars($package_code); ?>"
                                    placeholder="Package Activation Code"
                                    required
                                >
                                <label for="packageCode">Package Activation Code</label>
                            </div>

                            <div class="lookup-panel" id="packagePreview"></div>
                        </div>
                    </div>
                </div>

                <div class="step-card">
                    <div class="step-heading">
                        <span class="step-number">3</span>
                        <h4>Security</h4>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    placeholder="Password"
                                    minlength="8"
                                    required
                                >
                                <label for="password">Password</label>
                            </div>
                            <div class="password-meter" id="passwordMeter">
                                <div class="meter-track">
                                    <div class="meter-fill"></div>
                                </div>
                                <span class="meter-label" id="passwordStrengthLabel">Weak</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-floating">
                                <input
                                    type="password"
                                    name="confirm_password"
                                    id="confirmPassword"
                                    class="form-control"
                                    placeholder="Confirm Password"
                                    minlength="8"
                                    required
                                >
                                <label for="confirmPassword">Confirm Password</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="disclaimer-card">
                    <div class="disclaimer-header">
                        <span class="warning-icon" aria-hidden="true">&#9888;</span>
                        <h4>Member Awareness &amp; Risk Disclosure</h4>
                    </div>

                    <div class="disclaimer-box">
                        <strong>DISCLAIMER</strong>
                        <p>
                            VLD GLOBAL is strictly an Educational Platform focused on Forex trading knowledge and skills development.
                        </p>
                        <p>
                            We do not solicit investments, guarantee any income, profits, returns, or financial outcomes.
                        </p>
                        <p>
                            All trading decisions made by users are their sole responsibility.
                        </p>
                        <p>
                            Forex trading carries a high level of risk and may not be suitable for all individuals.
                        </p>
                        <p class="mb-1">By registering, I acknowledge that:</p>
                        <ul>
                            <li>I understand VLD Global is an educational platform.</li>
                            <li>I am not investing money with VLD Global.</li>
                            <li>I understand there is no guaranteed income.</li>
                            <li>I understand trading involves risk.</li>
                            <li>I take full responsibility for my own trading decisions.</li>
                        </ul>
                    </div>

                    <div class="form-check disclaimer-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="disclaimer_accepted"
                            id="disclaimerAccepted"
                            value="1"
                            required
                        >
                        <label class="form-check-label" for="disclaimerAccepted">
                            I have read and understood the VLD Global Disclaimer and Risk Disclosure.
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn register-btn w-100" id="registerButton" disabled>
                    Create My Account
                </button>
            </form>

            <div class="login-links">
                <a href="login.php">Already have an account? Login</a>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
<script>
    setTimeout(function () {
        window.location.replace("login.php");
    }, 1700);
</script>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var usernameInput = document.getElementById("username");
    var emailInput = document.getElementById("email");
    var contactInput = document.getElementById("contactNo");
    var sponsorInput = document.getElementById("sponsorUsername");
    var packageInput = document.getElementById("packageCode");
    var passwordInput = document.getElementById("password");
    var disclaimerAccepted = document.getElementById("disclaimerAccepted");
    var registerButton = document.getElementById("registerButton");
    var passwordMeter = document.getElementById("passwordMeter");
    var passwordStrengthLabel = document.getElementById("passwordStrengthLabel");
    var sponsorPreview = document.getElementById("sponsorPreview");
    var packagePreview = document.getElementById("packagePreview");

    function debounce(callback, delay) {
        var timer;
        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () {
                callback.apply(null, args);
            }, delay);
        };
    }

    function lookup(action, value) {
        return fetch("register.php?ajax=" + encodeURIComponent(action) + "&value=" + encodeURIComponent(value))
            .then(function (response) {
                return response.json();
            });
    }

    function setFeedback(elementId, type, message) {
        var element = document.getElementById(elementId);
        element.className = "field-feedback";

        if (!message) {
            element.textContent = "";
            return;
        }

        element.classList.add(type === "success" ? "is-success" : "is-danger");
        element.textContent = message;
    }

    function setLookupPanel(panel, type, html) {
        panel.className = "lookup-panel is-visible " + (type === "success" ? "is-success" : "is-danger");
        panel.innerHTML = html;
    }

    function hideLookupPanel(panel) {
        panel.className = "lookup-panel";
        panel.innerHTML = "";
    }

    function normalizeContact(value) {
        return value.trim().replace(/[\s-]+/g, "");
    }

    function updateRegisterButton() {
        registerButton.disabled = !disclaimerAccepted.checked;
    }

    var checkUsername = debounce(function () {
        var value = usernameInput.value.trim();
        usernameInput.value = value.toLowerCase();
        value = usernameInput.value;

        if (!value) {
            setFeedback("usernameFeedback", "danger", "");
            return;
        }

        lookup("username", value).then(function (data) {
            setFeedback("usernameFeedback", data.valid && data.available ? "success" : "danger", data.message);
        });
    }, 350);

    var checkEmail = debounce(function () {
        var value = emailInput.value.trim();

        if (!value) {
            setFeedback("emailFeedback", "danger", "");
            return;
        }

        lookup("email", value).then(function (data) {
            setFeedback("emailFeedback", data.valid && data.available ? "success" : "danger", data.message);
        });
    }, 350);

    var checkContact = debounce(function () {
        var value = contactInput.value.trim();

        if (!value) {
            setFeedback("contactFeedback", "danger", "");
            return;
        }

        lookup("contact", value).then(function (data) {
            if (data.normalized && data.valid) {
                contactInput.value = data.normalized;
            }

            setFeedback("contactFeedback", data.valid && data.available ? "success" : "danger", data.message);
        });
    }, 350);

    var checkSponsor = debounce(function () {
        var value = sponsorInput.value.trim().toLowerCase();
        sponsorInput.value = value;

        if (!value) {
            hideLookupPanel(sponsorPreview);
            return;
        }

        lookup("sponsor", value).then(function (data) {
            if (!data.found) {
                setLookupPanel(sponsorPreview, "danger", "<div class=\"lookup-title\">&#10006; Sponsor Not Found</div>");
                return;
            }

            setLookupPanel(
                sponsorPreview,
                "success",
                "<div class=\"lookup-title\">&#10004; Sponsor Found</div>" +
                "<div class=\"lookup-row\"><span>Full Name</span><span>" + escapeHtml(data.sponsor.full_name || "") + "</span></div>" +
                "<div class=\"lookup-row\"><span>Member Code</span><span>" + escapeHtml(data.sponsor.member_code || "") + "</span></div>"
            );
        });
    }, 350);

    var checkPackage = debounce(function () {
        var value = packageInput.value.trim().toUpperCase();
        packageInput.value = value;

        if (!value) {
            hideLookupPanel(packagePreview);
            return;
        }

        lookup("package", value).then(function (data) {
            if (!data.found) {
                setLookupPanel(packagePreview, "danger", "<div class=\"lookup-title\">&#10006; " + escapeHtml(data.message) + "</div>");
                return;
            }

            setLookupPanel(
                packagePreview,
                "success",
                "<div class=\"lookup-title\">&#10004; Package Code Found</div>" +
                "<div class=\"lookup-row\"><span>Package</span><span>" + escapeHtml(data.package.name || "") + "</span></div>" +
                "<div class=\"lookup-row\"><span>Amount</span><span>\u20B1" + escapeHtml(data.package.price || "0.00") + "</span></div>"
            );
        });
    }, 350);

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    function updatePasswordStrength() {
        var password = passwordInput.value;
        var score = 0;
        var label = "Weak";
        var level = "weak";

        if (!password) {
            passwordMeter.className = "password-meter";
            passwordStrengthLabel.textContent = "Weak";
            return;
        }

        if (password.length >= 8) {
            score++;
        }

        if (password.length >= 12) {
            score++;
        }

        if (/[a-z]/.test(password) && /[A-Z]/.test(password)) {
            score++;
        }

        if (/\d/.test(password)) {
            score++;
        }

        if (/[^A-Za-z0-9]/.test(password)) {
            score++;
        }

        if (score >= 4) {
            label = "Strong";
            level = "strong";
        } else if (score >= 2) {
            label = "Medium";
            level = "medium";
        }

        passwordMeter.className = "password-meter is-visible " + level;
        passwordStrengthLabel.textContent = label;
    }

    usernameInput.addEventListener("input", checkUsername);
    emailInput.addEventListener("input", checkEmail);
    contactInput.addEventListener("input", checkContact);
    contactInput.addEventListener("blur", function () {
        contactInput.value = normalizeContact(contactInput.value);
        checkContact();
    });
    sponsorInput.addEventListener("input", checkSponsor);
    packageInput.addEventListener("input", checkPackage);
    passwordInput.addEventListener("input", updatePasswordStrength);
    disclaimerAccepted.addEventListener("change", updateRegisterButton);

    updateRegisterButton();

    if (usernameInput.value) {
        checkUsername();
    }

    if (emailInput.value) {
        checkEmail();
    }

    if (contactInput.value) {
        checkContact();
    }

    if (sponsorInput.value) {
        checkSponsor();
    }

    if (packageInput.value) {
        checkPackage();
    }
});
</script>

</body>
</html>
