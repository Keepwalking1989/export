<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root'); // Default XAMPP username
define('DB_PASSWORD', '');     // Default XAMPP password
define('DB_NAME', 'erp_db');       // We'll name our database 'erp_db'

/* Attempt to connect to MySQL database */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if($conn === false){
    die("ERROR: Could not connect. " . $conn->connect_error);
}

// Attempt to create database if it doesn't exist
$sql_create_db = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if($conn->query($sql_create_db) === TRUE){
    // echo "Database created successfully or already exists.\n";
} else {
    die("ERROR: Could not create database. " . $conn->error);
}

// Now connect to the specific database
$conn->select_db(DB_NAME);
if($conn->error){
    die("ERROR: Could not select database " . DB_NAME . ". " . $conn->error);
}

// echo "Successfully connected to " . DB_NAME . ".\n";

// Set charset to utf8mb4 for broader character support
if (!$conn->set_charset("utf8mb4")) {
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
} else {
    // printf("Current character set: %s\n", $conn->character_set_name());
}

// Function to close connection (optional, as PHP usually closes it at script end)
function close_connection($conn_to_close){
    $conn_to_close->close();
}
?>
