<?php

// router.php - Router file for Symfony with PHP built-in web server

use Symfony\Component\HttpFoundation\Request;

// Check if the requested file is a real file and serve it directly
if (is_file($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $_SERVER['REQUEST_URI'])) {
    return false;
}

// Set the script name to the front controller
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'index.php';

// Include the Symfony front controller
require_once $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'index.php';
