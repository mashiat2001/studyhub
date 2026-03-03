<?php
// sslcommerz_config.php

// Your sandbox credentials (replace with your actual store ID and password)
define('SSLC_STORE_ID', 'study6995d2ca60f81');      // Replace with your sandbox Store ID
define('SSLC_STORE_PASSWORD', 'study6995d2ca60f81@ssl'); // Replace with your sandbox password
define('SSLC_IS_SANDBOX', true); // true for sandbox, false for live

// Base URL – update with your actual domain or ngrok URL
// If testing locally without ngrok, you can use localhost, but SSLCommerz won't be able to send IPN.
// For testing, it's recommended to use ngrok or a live server.
define('BASE_URL', 'http://localhost/studyhub'); // Change to your actual base URL

// Callback URLs
define('SSLC_SUCCESS_URL', BASE_URL . '/payment_success.php');
define('SSLC_FAIL_URL', BASE_URL . '/payment_fail.php');
define('SSLC_CANCEL_URL', BASE_URL . '/payment_cancel.php');
define('SSLC_IPN_URL', BASE_URL . '/payment_ipn.php');
?>