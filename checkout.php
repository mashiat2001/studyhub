<?php
session_start();
require_once 'sslcommerz_config.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user']['id'];
$course_id = $_GET['course_id'] ?? 0;

$conn = new mysqli('localhost', 'root', '', 'project_db');
if ($conn->connect_error) {
    die("Connection failed");
}

// Get course details
$stmt = $conn->prepare("SELECT title, price FROM courses WHERE id = ?");
$stmt->bind_param("i", $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();

if (!$course) {
    die("Course not found");
}

// Get user details
$user_stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user = $user_stmt->get_result()->fetch_assoc();

// Generate unique transaction ID
$tran_id = uniqid('TXN-');

// Insert pending order
$insert = $conn->prepare("INSERT INTO orders (user_id, course_id, amount, transaction_id, status) VALUES (?, ?, ?, ?, 'pending')");
$insert->bind_param("iids", $user_id, $course_id, $course['price'], $tran_id);
$insert->execute();
$order_id = $conn->insert_id;
$conn->close();

// Prepare SSLCommerz request data
$post_data = [
    'store_id'          => SSLC_STORE_ID,
    'store_passwd'      => SSLC_STORE_PASSWORD,
    'total_amount'      => $course['price'],
    'currency'          => 'BDT',
    'tran_id'           => $tran_id,
    'success_url'       => SSLC_SUCCESS_URL . '?order_id=' . $order_id,
    'fail_url'          => SSLC_FAIL_URL . '?order_id=' . $order_id,
    'cancel_url'        => SSLC_CANCEL_URL . '?order_id=' . $order_id,
    'ipn_url'           => SSLC_IPN_URL,
    'cus_name'          => $user['name'],
    'cus_email'         => $user['email'],
    'cus_add1'          => 'Customer Address',
    'cus_city'          => 'Dhaka',
    'cus_country'       => 'Bangladesh',
    'cus_phone'         => '01700000000',
    'product_name'      => $course['title'],
    'product_category'  => 'Course',
    'product_profile'   => 'general',
    // Custom field to pass order_id (so it can be retrieved from validation response)
    'value_a'           => $order_id
];

$api_url = SSLC_IS_SANDBOX ? 'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' : 'https://secure.sslcommerz.com/gwprocess/v4/api.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code != 200) {
    die("Payment gateway error. Please try again.");
}

$result = json_decode($response, true);

if ($result['status'] == 'SUCCESS') {
    header("Location: " . $result['GatewayPageURL']);
    exit();
} else {
    echo "<h3>Payment initiation failed</h3>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
}
?>