<?php
require __DIR__ . '/includes/config.php';

session_unset();
session_destroy();
session_start();
session_regenerate_id(true);
$_SESSION['flash_message'] = 'You have been logged out.';
redirect('index.php');
