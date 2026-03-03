<?php
session_start();
require_once 'sslcommerz_config.php';

// Accept only POST from SSLCommerz
$val_id = $_POST['val_id'] ?? '';

if (!$val_id) {
    die("Invalid access. No validation ID received.");
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die("Database connection failed.");
}

/* ==============================
   STEP 1: VALIDATE PAYMENT
================================= */

$validation_url = SSLC_IS_SANDBOX
    ? "https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php"
    : "https://secure.sslcommerz.com/validator/api/validationserverAPI.php";

$validation_url .= "?val_id=" . urlencode($val_id)
    . "&store_id=" . SSLC_STORE_ID
    . "&store_passwd=" . SSLC_STORE_PASSWORD
    . "&format=json";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $validation_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
curl_close($ch);

$validationArr = json_decode($response, true);

if (!$validationArr || !isset($validationArr['status'])) {
    die("Payment validation failed.");
}

if ($validationArr['status'] !== 'VALID') {
    die("Payment not valid.");
}

/* ==============================
   STEP 2: GET ORDER ID
================================= */

$order_id = intval($validationArr['value_a'] ?? 0);
if (!$order_id) {
    die("Order ID missing.");
}

/* ==============================
   STEP 3: FETCH ORDER
================================= */

$order_stmt = $conn->prepare("SELECT user_id, course_id, amount, status FROM orders WHERE id = ?");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order = $order_stmt->get_result()->fetch_assoc();

if (!$order) {
    die("Order not found.");
}

// Prevent duplicate processing
if ($order['status'] === 'paid') {
    header("Location: student_dashboard.php");
    exit();
}

/* ==============================
   STEP 4: VERIFY AMOUNT
================================= */

if (floatval($validationArr['amount']) != floatval($order['amount'])) {
    die("Amount mismatch detected.");
}

/* ==============================
   STEP 5: UPDATE ORDER
================================= */

$bank_tran_id = $validationArr['bank_tran_id'] ?? '';

$update = $conn->prepare("UPDATE orders SET status = 'paid', bank_tran_id = ? WHERE id = ?");
$update->bind_param("si", $bank_tran_id, $order_id);
$update->execute();

/* ==============================
   STEP 6: ENROLL STUDENT
================================= */

$enroll = $conn->prepare("INSERT IGNORE INTO user_courses (user_id, course_id, progress) VALUES (?, ?, 0)");
$enroll->bind_param("ii", $order['user_id'], $order['course_id']);
$enroll->execute();

/* ==============================
   STEP 7: RESTORE SESSION
================================= */

$user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->bind_param("i", $order['user_id']);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();

$_SESSION['user'] = $user_data;

/* ==============================
   STEP 8: SUCCESS MESSAGE
================================= */

$_SESSION['payment_success'] = "Payment successful! You are now enrolled.";

/* ==============================
   STEP 9: REDIRECT
================================= */

header("Location: student_dashboard.php");
exit();
?>