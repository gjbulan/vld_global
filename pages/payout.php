

<?php
$member_id = $_SESSION['member_id'];
$balance = getBalance($conn, $member_id);

$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount']);

    if ($amount > $balance) {
        $error = "Insufficient balance.";
    } elseif ($amount <= 0) {
        $error = "Invalid amount.";
    } else {
        $fee_percent = $amount * 0.10;
        $flat_fee = 100;
        $total_fee = $fee_percent + $flat_fee;
        $net = $amount - $total_fee;

        $stmt = $conn->prepare("
            INSERT INTO payouts (member_id, amount, fee, net_amount)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("iddd", $member_id, $amount, $total_fee, $net);
        $stmt->execute();

        addBonus($conn, $member_id, -$amount, "payout", "Payout request");

        $success = "Payout requested successfully.";
    }
}
?>

<h4>Request Payout</h4>

<div class="card mt-3">
    <div class="card-body">

        <p><strong>Available Balance:</strong> ₱<?php echo number_format($balance,2); ?></p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label>Amount</label>
                <input type="number" step="0.01" name="amount" class="form-control" required>
            </div>

            <button class="btn btn-success">Request Payout</button>
        </form>
    </div>
</div>