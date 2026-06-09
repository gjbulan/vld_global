<?php
$success = "";
$error = "";

try {
    seedDefaultProducts($conn);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$result = $conn->query("
    SELECT id, name, personal_bonus, community_bonus, status, created_at
    FROM products
    WHERE id IN (1, 2)
    ORDER BY id ASC
");
?>

<h4>Products</h4>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-header">Default Product Catalog</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Personal Bonus</th>
                        <th>Community Bonus</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['personal_bonus'], 2); ?></td>
                            <td>&#8369;<?php echo number_format((float)$row['community_bonus'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
