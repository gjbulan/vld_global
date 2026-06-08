

<?php
$success = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);

        if ($name === "") {
            $error = "Product name is required.";
        } else {
            $stmt = $conn->prepare("INSERT INTO products (name) VALUES (?)");
            $stmt->bind_param("s", $name);

            if ($stmt->execute()) {
                $success = "Product added successfully.";
            } else {
                $error = "Failed to add product.";
            }
        }
    }

    if (isset($_POST['update_product'])) {
        $id = intval($_POST['id']);
        $name = trim($_POST['name']);

        $stmt = $conn->prepare("UPDATE products SET name=? WHERE id=?");
        $stmt->bind_param("si", $name, $id);

        if ($stmt->execute()) {
            $success = "Product updated successfully.";
        } else {
            $error = "Failed to update product.";
        }
    }
}

$result = $conn->query("SELECT * FROM products ORDER BY id DESC");
?>

<h4>Products</h4>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger mt-3"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-header">Add Product</div>
    <div class="card-body">
        <form method="POST" class="row">
            <div class="col-md-10">
                <input type="text" name="name" class="form-control" placeholder="Product name" required>
            </div>
            <div class="col-md-2">
                <button name="add_product" class="btn btn-success w-100">Add</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-3">
    <div class="card-header">Product List</div>
    <div class="card-body">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Product Name</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <form method="POST">
                            <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($row['name']); ?>">
                            </td>
                            <td>
                                <button name="update_product" class="btn btn-primary btn-sm">Update</button>
                            </td>
                        </form>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>