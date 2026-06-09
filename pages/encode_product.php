<?php
$success = "";
$error = "";
$encoded_summary = null;

seedDefaultProducts($conn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $member_id = (int)$_SESSION['member_id'];

    if ($code === "") {
        $error = "Product code is required.";
    } else {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                SELECT
                    pc.id,
                    pc.code,
                    pc.product_id,
                    pc.quantity,
                    p.name AS product_name,
                    p.personal_bonus,
                    p.community_bonus
                FROM product_codes pc
                JOIN products p ON pc.product_id = p.id
                WHERE pc.code=? AND pc.status='unused' AND p.status='active'
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->bind_param("s", $code);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_assoc();

            if (!$data) {
                throw new Exception("Invalid, inactive, or already used product code.");
            }

            $product_code_id = (int)$data['id'];
            $product_id = (int)$data['product_id'];
            $quantity = (int)$data['quantity'];

            if ($quantity <= 0) {
                throw new Exception("Product code quantity is invalid.");
            }

            $stmt = $conn->prepare("
                INSERT INTO product_purchases (member_id, product_id, quantity, product_code_id)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiii", $member_id, $product_id, $quantity, $product_code_id);

            if (!$stmt->execute()) {
                throw new Exception("Unable to record product purchase.");
            }

            $product_purchase_id = $conn->insert_id;

            $stmt = $conn->prepare("
                UPDATE product_codes
                SET status='used', used_by_member_id=?, used_at=NOW()
                WHERE id=? AND status='unused'
            ");
            $stmt->bind_param("ii", $member_id, $product_code_id);

            if (!$stmt->execute() || $stmt->affected_rows !== 1) {
                throw new Exception("Product code could not be reserved.");
            }

            $personal_result = processPersonalPurchaseBonus($conn, $member_id, $product_id, $quantity);
            $community_results = processCommunityBonus($conn, $member_id, $product_id, $quantity, $product_purchase_id, $product_code_id);

            $conn->commit();

            $personal_amount = $personal_result['awarded'] ? (float)$personal_result['amount'] : 0.00;
            $community_rows = count($community_results);

            $success = "Product encoded successfully.";
            $encoded_summary = [
                'product_name' => $data['product_name'],
                'quantity' => $quantity,
                'personal_amount' => $personal_amount,
                'community_rows' => $community_rows
            ];
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$monthly_purchase_count = getMonthlyProductPurchaseCount($conn, $_SESSION['member_id']);
$community_qualified = $monthly_purchase_count >= 2;
?>

<div class="premium-card">
    <div class="card-title-row">
        <h5>Encode Product Code</h5>
        <span><?php echo $community_qualified ? 'Community Qualified' : 'Needs 2 Products This Month'; ?></span>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($encoded_summary): ?>
        <div class="table-responsive mb-4">
            <table class="table premium-table mb-0">
                <tr>
                    <th>Product</th>
                    <td><?php echo htmlspecialchars($encoded_summary['product_name']); ?></td>
                </tr>
                <tr>
                    <th>Quantity</th>
                    <td><?php echo (int)$encoded_summary['quantity']; ?></td>
                </tr>
                <tr>
                    <th>Personal Purchase Bonus</th>
                    <td>&#8369;<?php echo number_format((float)$encoded_summary['personal_amount'], 2); ?></td>
                </tr>
                <tr>
                    <th>Qualified Upline Bonuses Created</th>
                    <td><?php echo (int)$encoded_summary['community_rows']; ?></td>
                </tr>
            </table>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Enter Product Code</label>
                    <input type="text" name="code" class="form-control premium-input" required>
                </div>

                <button class="btn copy-btn">Submit</button>
            </form>
        </div>

        <div class="col-lg-4">
            <table class="table premium-table mb-0">
                <tr>
                    <th>Products Bought This Month</th>
                    <td><?php echo $monthly_purchase_count; ?>/2</td>
                </tr>
                <tr>
                    <th>Community Qualified</th>
                    <td><?php echo $community_qualified ? 'Yes' : 'No'; ?></td>
                </tr>
                <tr>
                    <th>Personal Bonus</th>
                    <td>Always available</td>
                </tr>
            </table>
        </div>
    </div>
</div>
