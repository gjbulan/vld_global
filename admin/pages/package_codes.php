
<?php
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $package_id = intval($_POST['package_id']);
    $quantity = intval($_POST['quantity']);

    if ($quantity <= 0) {
        $error = "Invalid quantity.";
    } else {
        for ($i = 1; $i <= $quantity; $i++) {
            $code = "PKG-" . strtoupper(bin2hex(random_bytes(4))) . "-" . time() . "-" . $i;

            $stmt = $conn->prepare("
                INSERT INTO package_codes (code, package_id, status)
                VALUES (?, ?, 'unused')
            ");
            $stmt->bind_param("si", $code, $package_id);
            $stmt->execute();
        }

        $success = "Package codes generated successfully.";
    }
}

$packages = $conn->query("SELECT * FROM packages ORDER BY id ASC");

$codes = $conn->query("
    SELECT pc.*, p.name AS package_name, m.username AS used_by
    FROM package_codes pc
    JOIN packages p ON pc.package_id = p.id
    LEFT JOIN members m ON pc.used_by_member_id = m.id
    ORDER BY pc.id DESC
");
?>

<h4>Package Codes</h4>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-header">Generate Package Codes</div>
    <div class="card-body">
        <form method="POST" class="row">
            <div class="col-md-5">
                <label>Package</label>
                <select name="package_id" class="form-control" required>
                    <?php while ($pkg = $packages->fetch_assoc()): ?>
                        <option value="<?php echo $pkg['id']; ?>">
                            <?php echo htmlspecialchars($pkg['name']); ?> - ₱<?php echo number_format($pkg['price'], 2); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-5">
                <label>Quantity</label>
                <input type="number" name="quantity" class="form-control" min="1" required>
            </div>

            <div class="col-md-2">
                <label>&nbsp;</label>
                <button class="btn btn-success w-100">Generate</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Package Code List</div>
    <div class="card-body">
        <table class="table table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Package</th>
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
                        <td><?php echo htmlspecialchars($row['package_name']); ?></td>
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