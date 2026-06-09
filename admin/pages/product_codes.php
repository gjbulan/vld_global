<?php
$success = "";
$error = "";

try {
    seedDefaultProducts($conn);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $codes_count = (int)($_POST['codes_count'] ?? 0);

    if ($quantity <= 0 || $codes_count <= 0) {
        $error = "Invalid quantity or number of codes.";
    } else {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                SELECT id
                FROM products
                WHERE id=? AND id IN (1, 2) AND status='active'
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();

            if (!$stmt->get_result()->fetch_assoc()) {
                throw new Exception("Product is not available for code generation.");
            }

            $stmt = $conn->prepare("
                INSERT INTO product_codes (code, product_id, quantity, status)
                VALUES (?, ?, ?, 'unused')
            ");

            for ($i = 1; $i <= $codes_count; $i++) {
                $code = "PRD-" . strtoupper(bin2hex(random_bytes(4))) . "-" . time() . "-" . $i;
                $stmt->bind_param("sii", $code, $product_id, $quantity);

                if (!$stmt->execute()) {
                    throw new Exception("Unable to generate product code.");
                }
            }

            $conn->commit();
            $success = "Product codes generated successfully.";
        } catch (Throwable $e) {
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

$products = $conn->query("
    SELECT id, name, personal_bonus, community_bonus
    FROM products
    WHERE id IN (1, 2) AND status='active'
    ORDER BY id ASC
");

$codes = $conn->query("
    SELECT
        pc.*,
        p.name AS product_name,
        p.personal_bonus,
        p.community_bonus,
        m.username AS used_by
    FROM product_codes pc
    LEFT JOIN products p ON pc.product_id = p.id
    LEFT JOIN members m ON pc.used_by_member_id = m.id
    ORDER BY pc.id DESC
    LIMIT 500
");
?>

<h4>Product Codes</h4>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-header">Generate Product Codes</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-lg-4 col-md-6">
                <label class="form-label">Product</label>
                <select name="product_id" class="form-control" required>
                    <?php while ($p = $products->fetch_assoc()): ?>
                        <option value="<?php echo (int)$p['id']; ?>">
                            <?php echo htmlspecialchars($p['name']); ?>
                            (Personal &#8369;<?php echo number_format((float)$p['personal_bonus'], 2); ?> /
                            Community &#8369;<?php echo number_format((float)$p['community_bonus'], 2); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Quantity per Code</label>
                <input type="number" name="quantity" class="form-control" min="1" required>
            </div>

            <div class="col-lg-3 col-md-6">
                <label class="form-label">Number of Codes</label>
                <input type="number" name="codes_count" class="form-control" min="1" required>
            </div>

            <div class="col-lg-2 col-md-6 d-flex align-items-end">
                <button class="btn btn-success w-100">Generate</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Product Code List</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-sm align-middle">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Personal Bonus</th>
                        <th>Community Bonus</th>
                        <th>Status</th>
                        <th>Used By</th>
                        <th>Created</th>
                        <th>Used At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $codes->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['code']); ?></td>
                            <td><?php echo htmlspecialchars($row['product_name'] ?? 'Inactive/Removed Product'); ?></td>
                            <td><?php echo (int)$row['quantity']; ?></td>
                            <td>&#8369;<?php echo number_format((float)($row['personal_bonus'] ?? 0), 2); ?></td>
                            <td>&#8369;<?php echo number_format((float)($row['community_bonus'] ?? 0), 2); ?></td>
                            <td><?php echo htmlspecialchars($row['status']); ?></td>
                            <td><?php echo htmlspecialchars($row['used_by'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($row['used_at'] ?? ''); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
