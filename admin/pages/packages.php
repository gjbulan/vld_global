
<?php
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $direct_bonus = floatval($_POST['direct_bonus']);
    $generation_bonus = floatval($_POST['generation_bonus']);

    $stmt = $conn->prepare("
        UPDATE packages
        SET name=?, price=?, direct_bonus=?, generation_bonus=?
        WHERE id=?
    ");
    $stmt->bind_param("sdddi", $name, $price, $direct_bonus, $generation_bonus, $id);

    if ($stmt->execute()) {
        $success = "Package updated successfully.";
    } else {
        $error = "Failed to update package.";
    }
}

$result = $conn->query("SELECT * FROM packages ORDER BY id ASC");
?>

<h4>Packages</h4>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Package</th>
                    <th>Price</th>
                    <th>Direct Bonus</th>
                    <th>Generation Bonus</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <td>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($row['name']); ?>" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $row['price']; ?>" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="direct_bonus" class="form-control" value="<?php echo $row['direct_bonus']; ?>" required>
                            </td>
                            <td>
                                <input type="number" step="0.01" name="generation_bonus" class="form-control" value="<?php echo $row['generation_bonus']; ?>" required>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-sm">Update</button>
                            </td>
                        </form>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>