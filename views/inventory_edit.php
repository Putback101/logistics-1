<?php
session_start();
require_once __DIR__ . "/../config/auth.php";
require_once __DIR__ . "/../config/database.php";
require_once __DIR__ . "/../models/Inventory.php";

$inventory = new Inventory($pdo);
$item = $inventory->getById($_GET['id']);
?>

<?php include "layout/header.php"; ?>

<h2>Edit Inventory Item</h2>

<form method="POST" action="../controllers/InventoryController.php">
    <input type="hidden" name="id" value="<?= $item['id'] ?>">

    <input type="text" name="item_name"
           value="<?= $item['item_name'] ?>" required>

    <input type="number" name="stock"
           value="<?= $item['stock'] ?>" required>

    <input type="text" name="location"
           value="<?= $item['location'] ?>" required>

    <button type="submit" name="update">Update Item</button>
</form>

<?php include "layout/footer.php"; ?>



