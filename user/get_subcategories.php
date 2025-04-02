<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
    echo json_encode([]);
    exit;
}

$category_id = filter_input(INPUT_GET, 'category_id', FILTER_SANITIZE_NUMBER_INT);
$mysqli = db_connect();

$query = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name";
$stmt = $mysqli->prepare($query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

$subcategories = [];
while ($row = $result->fetch_assoc()) {
    $subcategories[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}

echo json_encode($subcategories);