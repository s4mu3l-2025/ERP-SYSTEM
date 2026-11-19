<?php
require_once __DIR__ . "../config/database.php";

$modules = [
    "products" => [
        "title" => "Products",
        "table" => "products",
        "fields" => ["name" => "Product Name", "price" => "Price", "stock" => "Stock"],
        "types" => ["name" => "text", "price" => "number", "stock" => "number"],
    ],
    "customers" => [
        "title" => "Customers",
        "table" => "customers",
        "fields" => ["name" => "Customer Name", "phone" => "Phone", "email" => "Email"],
        "types" => ["name" => "text", "phone" => "text", "email" => "email"],
    ],
    "employees" => [
        "title" => "Employees",
        "table" => "employees",
        "fields" => ["name" => "Employee Name", "role" => "Job Role", "phone" => "Phone"],
        "types" => ["name" => "text", "role" => "text", "phone" => "text"],
    ],
];

$module = $_GET["module"] ?? "dashboard";
if (!isset($modules[$module]) && $module !== "dashboard" && $module !== "sales") {
    $module = "dashboard";
}
$message = "";
$error = "";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function countRows($conn, $table) {
    $allowed = ["products", "customers", "employees", "sales"];
    if (!in_array($table, $allowed, true)) return 0;
    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");
    return $result ? (int)$result->fetch_assoc()["total"] : 0;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if (isset($modules[$module])) {
        $config = $modules[$module];
        $table = $config["table"];
        $columns = array_keys($config["fields"]);

        if ($action === "save") {
            $id = (int)($_POST["id"] ?? 0);
            $values = [];
            foreach ($columns as $column) {
                $values[$column] = trim($_POST[$column] ?? "");
            }

            $valid = true;
            foreach ($values as $column => $value) {
                if ($value === "") $valid = false;
            }
            if (!$valid) {
                $error = "Please fill in all fields.";
            } elseif (($module === "products") && (!is_numeric($values["price"]) || !is_numeric($values["stock"]) || $values["price"] < 0 || $values["stock"] < 0)) {
                $error = "Price and stock must be valid non-negative numbers.";
            } else {
                if ($id > 0) {
                    $set = implode(" = ?, ", $columns) . " = ?";
                    $sql = "UPDATE `$table` SET $set WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $types = str_repeat("s", count($columns)) . "i";
                    $params = array_values($values);
                    $params[] = $id;
                    $stmt->bind_param($types, ...$params);
                } else {
                    $marks = implode(", ", array_fill(0, count($columns), "?"));
                    $columnList = implode(", ", $columns);
                    $stmt = $conn->prepare("INSERT INTO `$table` ($columnList) VALUES ($marks)");
                    $types = str_repeat("s", count($columns));
                    $params = array_values($values);
                    $stmt->bind_param($types, ...$params);
                }

                if ($stmt && $stmt->execute()) {
                    header("Location: index.php?module=" . urlencode($module) . "&saved=1");
                    exit;
                } else {
                    $error = "Could not save the record. Please check the database.";
                }
            }
        }

        if ($action === "delete") {
            $id = (int)($_POST["id"] ?? 0);
            if ($id > 0) {
                $stmt = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    header("Location: index.php?module=" . urlencode($module) . "&deleted=1");
                    exit;
                }
                $error = "Could not delete this record.";
            }
        }
    }

    if ($module === "sales" && $action === "add_sale") {
        $product_id = (int)($_POST["product_id"] ?? 0);
        $customer_id = (int)($_POST["customer_id"] ?? 0);
        $quantity = (int)($_POST["quantity"] ?? 0);

        $stmt = $conn->prepare("SELECT price, stock FROM products WHERE id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();

        if (!$product || $customer_id <= 0 || $quantity <= 0) {
            $error = "Please select a product and customer and enter a valid quantity.";
        } elseif ($quantity > (int)$product["stock"]) {
            $error = "Not enough stock available.";
        } else {
            $total = (float)$product["price"] * $quantity;
            $conn->begin_transaction();
            $sale = $conn->prepare("INSERT INTO sales (product_id, customer_id, quantity, total) VALUES (?, ?, ?, ?)");
            $sale->bind_param("iiid", $product_id, $customer_id, $quantity, $total);
            $stock = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $stock->bind_param("ii", $quantity, $product_id);
            if ($sale->execute() && $stock->execute()) {
                $conn->commit();
                header("Location: index.php?module=sales&saved=1");
                exit;
            } else {
                $conn->rollback();
                $error = "Sale could not be saved.";
            }
        }
    }
}

if (isset($_GET["saved"])) $message = "Saved successfully.";
if (isset($_GET["deleted"])) $message = "Deleted successfully.";

$editRecord = null;
if (isset($modules[$module]) && isset($_GET["edit"])) {
    $id = (int)$_GET["edit"];
    $table = $modules[$module]["table"];
    $stmt = $conn->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editRecord = $stmt->get_result()->fetch_assoc();
}

$rows = [];
if (isset($modules[$module])) {
    $table = $modules[$module]["table"];
    $result = $conn->query("SELECT * FROM `$table` ORDER BY id DESC");
    if ($result) $rows = $result->fetch_all(MYSQLI_ASSOC);
}

$products = $customers = $sales = [];
if ($module === "sales") {
    $productsResult = $conn->query("SELECT id, name, price, stock FROM products ORDER BY name");
    $customersResult = $conn->query("SELECT id, name FROM customers ORDER BY name");
    $salesResult = $conn->query(
        "SELECT s.id, p.name AS product_name, c.name AS customer_name, s.quantity, s.total, s.created_at
         FROM sales s JOIN products p ON p.id=s.product_id
         JOIN customers c ON c.id=s.customer_id ORDER BY s.id DESC"
    );
    if ($productsResult) $products = $productsResult->fetch_all(MYSQLI_ASSOC);
    if ($customersResult) $customers = $customersResult->fetch_all(MYSQLI_ASSOC);
    if ($salesResult) $sales = $salesResult->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple ERP System</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="brand"><span class="brand-icon">E</span><div><strong>EasyERP</strong><small>Management System</small></div></div>
        <div class="nav-label">MAIN MENU</div>
        <a class="<?= $module === 'dashboard' ? 'active' : '' ?>" href="index.php">▦ <span>Dashboard</span></a>
        <a class="<?= $module === 'products' ? 'active' : '' ?>" href="index.php?module=products">□ <span>Products</span></a>
        <a class="<?= $module === 'customers' ? 'active' : '' ?>" href="index.php?module=customers">♙ <span>Customers</span></a>
        <a class="<?= $module === 'employees' ? 'active' : '' ?>" href="index.php?module=employees">♧ <span>Employees</span></a>
        <a class="<?= $module === 'sales' ? 'active' : '' ?>" href="index.php?module=sales">₹ <span>Sales</span></a>
        <div class="sidebar-foot">Simple ERP • PHP + MySQL</div>
    </aside>

    <main class="main">
        <header class="topbar"><div><span class="eyebrow">WORKSPACE</span><h1><?= $module === "dashboard" ? "Dashboard" : h(ucfirst($module)) ?></h1></div><div class="admin">● <span>Admin</span></div></header>

        <?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= h($error) ?></div><?php endif; ?>

        <?php if ($module === "dashboard"): ?>
            <p class="welcome">Welcome back! Here is your business overview.</p>
            <div class="stats">
                <div class="stat-card"><div class="stat-icon purple">□</div><span>Total Products</span><strong><?= countRows($conn, "products") ?></strong><small>Items in inventory</small></div>
                <div class="stat-card"><div class="stat-icon blue">♙</div><span>Total Customers</span><strong><?= countRows($conn, "customers") ?></strong><small>Registered customers</small></div>
                <div class="stat-card"><div class="stat-icon orange">♧</div><span>Total Employees</span><strong><?= countRows($conn, "employees") ?></strong><small>Team members</small></div>
                <div class="stat-card"><div class="stat-icon green">₹</div><span>Total Sales</span><strong><?= countRows($conn, "sales") ?></strong><small>Sales transactions</small></div>
            </div>
            <section class="panel">
                <div class="panel-heading"><div><h2>Quick Actions</h2><p>Choose a section to manage your business</p></div></div>
                <div class="quick-links">
                    <a href="index.php?module=products">＋ Add / Manage Products</a>
                    <a href="index.php?module=customers">＋ Add / Manage Customers</a>
                    <a href="index.php?module=employees">＋ Add / Manage Employees</a>
                    <a href="index.php?module=sales">＋ Create / View Sales</a>
                </div>
            </section>
        <?php elseif (isset($modules[$module])): ?>
            <?php $config = $modules[$module]; ?>
            <section class="panel">
                <div class="panel-heading"><div><h2><?= $editRecord ? "Edit " : "Add " ?><?= h(rtrim($config["title"], "s")) ?></h2><p>Enter the details below and save.</p></div></div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= h($editRecord["id"] ?? 0) ?>">
                    <?php foreach ($config["fields"] as $field => $label): ?>
                        <label><?= h($label) ?>
                            <input type="<?= h($config["types"][$field]) ?>" name="<?= h($field) ?>" value="<?= h($editRecord[$field] ?? "") ?>" <?= in_array($field, ["price", "stock"], true) ? 'min="0" step="0.01"' : "" ?> required>
                        </label>
                    <?php endforeach; ?>
                    <div class="form-actions"><button class="btn primary" type="submit"><?= $editRecord ? "Update" : "Save" ?> <?= h(rtrim($config["title"], "s")) ?></button><?php if ($editRecord): ?><a class="btn light" href="index.php?module=<?= h($module) ?>">Cancel</a><?php endif; ?></div>
                </form>
            </section>
            <section class="panel">
                <div class="panel-heading"><div><h2>All <?= h($config["title"]) ?></h2><p><?= count($rows) ?> record(s) found</p></div></div>
                <div class="table-wrap"><table><thead><tr><th>ID</th><?php foreach ($config["fields"] as $label): ?><th><?= h($label) ?></th><?php endforeach; ?><th>Actions</th></tr></thead><tbody>
                <?php if (!$rows): ?><tr><td colspan="<?= count($config["fields"]) + 2 ?>" class="empty">No records yet. Add your first record above.</td></tr><?php endif; ?>
                <?php foreach ($rows as $row): ?><tr><td>#<?= h($row["id"]) ?></td><?php foreach ($config["fields"] as $field => $label): ?><td><?= h($row[$field]) ?></td><?php endforeach; ?><td class="actions"><a class="edit" href="index.php?module=<?= h($module) ?>&edit=<?= h($row["id"]) ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this record?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($row["id"]) ?>"><button class="delete" type="submit">Delete</button></form></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </section>
        <?php elseif ($module === "sales"): ?>
            <section class="panel">
                <div class="panel-heading"><div><h2>Create New Sale</h2><p>Select a product, customer and quantity.</p></div></div>
                <?php if (!$products || !$customers): ?><p class="hint">First add at least one product and one customer before creating a sale.</p><?php else: ?>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="add_sale">
                    <label>Product<select name="product_id" required><option value="">Select product</option><?php foreach ($products as $p): ?><option value="<?= h($p["id"]) ?>"><?= h($p["name"]) ?> — ₹<?= h($p["price"]) ?> (Stock: <?= h($p["stock"]) ?>)</option><?php endforeach; ?></select></label>
                    <label>Customer<select name="customer_id" required><option value="">Select customer</option><?php foreach ($customers as $c): ?><option value="<?= h($c["id"]) ?>"><?= h($c["name"]) ?></option><?php endforeach; ?></select></label>
                    <label>Quantity<input type="number" name="quantity" min="1" value="1" required></label>
                    <div class="form-actions"><button class="btn primary" type="submit">Save Sale</button></div>
                </form><?php endif; ?>
            </section>
            <section class="panel"><div class="panel-heading"><div><h2>Sales History</h2><p>Recent transactions</p></div></div><div class="table-wrap"><table><thead><tr><th>ID</th><th>Product</th><th>Customer</th><th>Quantity</th><th>Total</th><th>Date</th></tr></thead><tbody>
            <?php if (!$sales): ?><tr><td colspan="6" class="empty">No sales recorded yet.</td></tr><?php endif; ?>
            <?php foreach ($sales as $sale): ?><tr><td>#<?= h($sale["id"]) ?></td><td><?= h($sale["product_name"]) ?></td><td><?= h($sale["customer_name"]) ?></td><td><?= h($sale["quantity"]) ?></td><td>₹<?= number_format((float)$sale["total"], 2) ?></td><td><?= h($sale["created_at"]) ?></td></tr><?php endforeach; ?>
            </tbody></table></div></section>
        <?php endif; ?>
        <footer>© <?= date("Y") ?> EasyERP. Built with PHP & MySQL.</footer>
    </main>
</div>
</body>
</html>
