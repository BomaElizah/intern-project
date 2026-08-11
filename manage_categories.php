<?php
session_start();
include 'db_connect.php';

// Add Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_name'])) {
    $category_name = trim($_POST['category_name']);

    $stmt = $conn->prepare("INSERT INTO categories (category_name) VALUES (?)");
    $stmt->bind_param("s", $category_name);

    if ($stmt->execute()) {
        echo "Category added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}

// Delete Category
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    $delete_id = $_POST['delete_id'];

    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id=?");
    $stmt->bind_param("i", $delete_id);

    if ($stmt->execute()) {
        echo "Category deleted successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>
