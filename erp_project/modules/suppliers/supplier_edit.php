<?php
require_once '../../includes/db_connect.php'; // DB connect first

// Initialize variables
$supplier_id = null;
$name = $contact_person = $gst_number = $phone = $address = $product_category = "";
$errors = [];

// --- POST Request Processing ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["id"]) && !empty(trim($_POST["id"]))) {
    $supplier_id = trim($_POST["id"]);
    // Assign POST values for processing and sticky form
    $name = trim($_POST["name"]);
    $contact_person = trim($_POST["contact_person"]);
    $gst_number = trim($_POST["gst_number"]);
    $phone = trim($_POST["phone"]);
    $address = trim($_POST["address"]);
    $product_category = trim($_POST["product_category"]);

    if (empty($name)) {
        $errors["name"] = "Please enter a supplier name.";
    }
    // Add other validations...

    if (empty($errors)) {
        $sql_update = "UPDATE suppliers SET name=?, contact_person=?, gst_number=?, phone=?, address=?, product_category=? WHERE id=?";
        if ($stmt_update = $conn->prepare($sql_update)) {
            $stmt_update->bind_param("ssssssi", $name, $contact_person, $gst_number, $phone, $address, $product_category, $supplier_id);
            if ($stmt_update->execute()) {
                header("location: supplier_list.php?status=success_edit");
                exit();
            } else {
                $errors['db_error'] = "Error updating supplier: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors['db_error'] = "Error preparing statement: " . $conn->error;
        }
    }
}
// --- End of POST Request Processing ---
// --- GET Request Processing (for initial form load) ---
elseif (isset($_GET["id"]) && !empty(trim($_GET["id"])) && $_SERVER["REQUEST_METHOD"] != "POST") {
    $supplier_id = trim($_GET["id"]);
    $sql_fetch = "SELECT * FROM suppliers WHERE id = ?";
    if ($stmt_fetch = $conn->prepare($sql_fetch)) {
        $stmt_fetch->bind_param("i", $supplier_id);
        if ($stmt_fetch->execute()) {
            $result = $stmt_fetch->get_result();
            if ($result->num_rows == 1) {
                $row = $result->fetch_assoc();
                $name = $row['name'];
                $contact_person = $row['contact_person'];
                $gst_number = $row['gst_number'];
                $phone = $row['phone'];
                $address = $row['address'];
                $product_category = $row['product_category'];
            } else {
                $errors['load_error'] = "Error: Supplier not found for ID " . htmlspecialchars($supplier_id) . ".";
            }
        } else {
            $errors['load_error'] = "Error fetching supplier data: " . $stmt_fetch->error;
        }
        $stmt_fetch->close();
    } else {
        $errors['load_error'] = "Error preparing fetch statement: " . $conn->error;
    }
} elseif ($_SERVER["REQUEST_METHOD"] != "POST") {
     $errors['load_error'] = "No Supplier ID specified for editing.";
}
// --- End of GET Request Processing ---

require_once '../../includes/header.php'; // Include header AFTER processing

if ($_SERVER["REQUEST_METHOD"] != "POST" && !empty($errors['load_error'])) {
    echo "<div class='container mt-4'><div class='alert alert-danger'>" . htmlspecialchars($errors['load_error']) . " <a href='supplier_list.php' class='alert-link'>Back to List</a>.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}
?>

<div class="container">
    <h2>Edit Supplier</h2>
    <p>Please update the supplier details and submit.</p>

    <?php if (!empty($errors['db_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['db_error']); ?></div>
    <?php endif; ?>
    <?php if (($_SERVER["REQUEST_METHOD"] == "POST") && !empty($errors['load_error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($errors['load_error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
        <input type="hidden" name="id" value="<?php echo htmlspecialchars($supplier_id); ?>"/>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Supplier Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" value="<?php echo htmlspecialchars($name); ?>" required>
                    <span class="invalid-feedback"><?php echo $errors['name'] ?? '';?></span>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contact_person" class="form-control" value="<?php echo htmlspecialchars($contact_person); ?>">
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>GST Number</label>
                    <input type="text" name="gst_number" class="form-control" value="<?php echo htmlspecialchars($gst_number); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($phone); ?>">
                </div>
            </div>
        </div>

        <div class="form-group">
            <label>Address</label>
            <textarea name="address" class="form-control"><?php echo htmlspecialchars($address); ?></textarea>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Product Category</label>
                    <input type="text" name="product_category" class="form-control" value="<?php echo htmlspecialchars($product_category); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <!-- Placeholder for a potential next field in this row -->
            </div>
        </div>

        <div class="form-group mt-3">
            <input type="submit" class="btn btn-primary" value="Update">
            <a href="supplier_list.php" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php require_once '../../includes/footer.php'; ?>
