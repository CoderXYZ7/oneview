<?php
// Configuration

define('PRIVATE_DIR', __DIR__ . '/');
define('UPLOADS_DIR', PRIVATE_DIR . 'uploads/');
define('DATA_FILE', PRIVATE_DIR . 'data.json');

// Admin Credentials (Simplest possible auth for this scope)
// In a real scenario, use environment variables or a better auth system.
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD', 'password123'); // Change this!

// App Settings
define('DEFAULT_LOCK_TIMEOUT', 60); // minutes
define('LOG_FILE', PRIVATE_DIR . 'app.log');

// Setup Logging
ini_set('log_errors', 1);
ini_set('error_log', LOG_FILE);
error_reporting(E_ALL);

