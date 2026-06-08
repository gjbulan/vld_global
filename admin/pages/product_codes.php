

<?php
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $codes_count = intval($_POST['codes_count']);

    if ($quantity <= 0 || $codes_count <= 0) {
        $error = "Invalid quantity or number of codes.";
    } else {
        for ($i = 1; $i <= $codes_count; $i++) {
            $code = "PRD-" . strtoupper(bin2hex(random_bytes(4))) . "-" . time() . "-" . $i;

            $stmt = $conn->prepare("
                INSERT INTO product_codes (code, product_id, quantity, status)
                VALUES (?, ?, ?, 'unused')
            ");
            $stmt->bind_param("sii", $code, $product_id, $quantity);
            $stmt->execute();
        }

        $success = "Product codes generated successfully.";
    }
}

$products = $conn->query("SELECT * FROM products ORDER BY id ASC");

$codes = $conn->query("
    SELECT pc.*, p.name AS product_name, m.username AS used_by
    FROM product_codes pc
    JOIN products p ON pc.product_id = p.id
    LEFT JOIN members m ON pc.used_by_member_id = m.id
    ORDER BY pc.id DESC
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
        <form method="POST" class="row">
            <div class="col-md-4">
                <label>Product</label>
                <select name="product_id" class="form-control" required>
                    <?php while ($p = $products->fetch_assoc()): ?>
                        <option value="<?php echo $p['id']; ?>">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label>Quantity per Code</label>
                <input type="number" name="quantity" class="form-control" min="1" required>
            </div>

            <div class="col-md-3">
                <label>Number of Codes</label>
                <input type="number" name="codes_count" class="form-control" min="1" required>
            </div>

            <div class="col-md-2">
                <label>&nbsp;</label>
                <button class="btn btn-success w-100">Generate</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Product Code List</div>
    <div class="card-body">
        <table class="table table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Product</th>
                    <th>Qty</th>
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
                        <td><?php echo htmlspecialchars($row['product_name']); ?></td>
                        <td><?php echo $row['quantity']; ?></td>
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