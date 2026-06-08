
<?php
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['code']));
    $member_id = $_SESSION['member_id'];

    $stmt = $conn->prepare("SELECT * FROM product_codes WHERE code=? AND status='unused'");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();

    if (!$data) {
        $error = "Invalid or already used code.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO product_purchases (member_id, product_id, quantity)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $member_id, $data['product_id'], $data['quantity']);
        $stmt->execute();

        $stmt = $conn->prepare("
            UPDATE product_codes
            SET status='used', used_by_member_id=?, used_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("ii", $member_id, $data['id']);
        $stmt->execute();

        processCommunityBonus($conn, $member_id, $data['quantity']);

        $success = "Product encoded successfully.";
    }
}
?>

<h4>Encode Product Code</h4>

<div class="card mt-3">
    <div class="card-body">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label>Enter Product Code</label>
                <input type="text" name="code" class="form-control" required>
            </div>

            <button class="btn btn-primary">Submit</button>
        </form>
    </div>
</div>