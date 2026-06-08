

<?php
$member_id = $_SESSION['member_id'];

function normalizeProfileContactNumber($contact_no) {
    return preg_replace('/[\s-]+/', '', trim($contact_no));
}

function isValidProfileContactNumber($contact_no) {
    $normalized = normalizeProfileContactNumber($contact_no);
    return preg_match('/^\+?[0-9]{10,20}$/', $normalized);
}

function profileEmailExists($conn, $email, $member_id) {
    $stmt = $conn->prepare("SELECT id FROM members WHERE email=? AND id<>? LIMIT 1");
    $stmt->bind_param("si", $email, $member_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function profileContactExists($conn, $contact_no, $member_id) {
    $stmt = $conn->prepare("SELECT id FROM members WHERE contact_no=? AND id<>? LIMIT 1");
    $stmt->bind_param("si", $contact_no, $member_id);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

$stmt = $conn->prepare("
    SELECT m.*, p.name AS package_name
    FROM members m
    LEFT JOIN packages p ON m.package_id = p.id
    WHERE m.id=?
");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$cashback_status = getCashbackStatus($conn, $member_id);
$credit = $cashback_status['credit'];
$cashback = $cashback_status['cashback'];

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $contact_no = normalizeProfileContactNumber($_POST['contact_no']);

    if ($full_name === "") {
        $error = "Full name is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address.";
    } elseif (profileEmailExists($conn, $email, $member_id)) {
        $error = "Email address is already registered.";
    } elseif (!isValidProfileContactNumber($contact_no)) {
        $error = "Valid contact number is required.";
    } elseif (profileContactExists($conn, $contact_no, $member_id)) {
        $error = "Contact number is already registered.";
    } else {
        $stmt = $conn->prepare("UPDATE members SET full_name=?, email=?, contact_no=? WHERE id=?");
        $stmt->bind_param("sssi", $full_name, $email, $contact_no, $member_id);
        $stmt->execute();

        $member['full_name'] = $full_name;
        $member['email'] = $email;
        $member['contact_no'] = $contact_no;

        $success = "Profile updated.";
    }
}
?>

<h4>My Profile</h4>

<div class="card mt-3">
    <div class="card-body">

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="table-responsive mb-4">
            <table class="table premium-table">
                <tr>
                    <th>Current Package</th>
                    <td><?php echo htmlspecialchars($member['package_name'] ?? 'No Package'); ?></td>
                </tr>
                <tr>
                    <th>Cashback Status</th>
                    <td><?php echo $cashback ? 'Cashback earned' : htmlspecialchars($cashback_status['qualification_label']); ?></td>
                </tr>
                <tr>
                    <th>Dominance Advancement Credit</th>
                    <td>
                        <?php if ($credit): ?>
                            <?php echo htmlspecialchars($credit['status']); ?> -
                            &#8369;<?php echo number_format((float)$credit['amount'], 2); ?>
                        <?php else: ?>
                            No credit
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <form method="POST">
            <div class="mb-3">
                <label>Username</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($member['username']); ?>" readonly>
            </div>

            <div class="mb-3">
                <label>Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($member['full_name']); ?>" required>
            </div>

            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($member['email']); ?>" required>
            </div>

            <div class="mb-3">
                <label>Contact Number</label>
                <input
                    type="text"
                    name="contact_no"
                    id="profileContactNo"
                    class="form-control"
                    value="<?php echo htmlspecialchars($member['contact_no'] ?? ''); ?>"
                    maxlength="20"
                    pattern="[0-9+\-\s]{10,20}"
                    required
                >
            </div>

            <button class="btn btn-primary">Update</button>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var contactInput = document.getElementById("profileContactNo");

    if (!contactInput) {
        return;
    }

    contactInput.addEventListener("blur", function () {
        contactInput.value = contactInput.value.trim().replace(/[\s-]+/g, "");
    });
});
</script>
