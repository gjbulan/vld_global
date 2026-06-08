

<?php
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = "Settings saved.";
}
?>

<h4>Settings</h4>

<?php if ($success): ?>
    <div class="alert alert-success mt-3"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="card mt-3">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label>System Name</label>
                <input type="text" class="form-control" value="VLD Global Compensation System" readonly>
            </div>

            <div class="mb-3">
                <label>Payout Percentage Fee</label>
                <input type="text" class="form-control" value="10%" readonly>
            </div>

            <div class="mb-3">
                <label>Payout Flat Fee</label>
                <input type="text" class="form-control" value="₱100" readonly>
            </div>

            <div class="mb-3">
                <label>Community Bonus</label>
                <input type="text" class="form-control" value="₱5 per quantity per level up to 8 levels" readonly>
            </div>

           <!-- <button class="btn btn-primary">Save</button>!-->
        </form>
    </div>
</div>