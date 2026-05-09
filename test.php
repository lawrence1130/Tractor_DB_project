<?php
echo "Hello test";
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
echo "Base URL: " . getBaseUrl();
?>