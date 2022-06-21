#!/usr/bin/php
<?php

$appDir = dirname(__FILE__);
require_once($appDir.'/config.php');

print "User / Servername: ";
$user = trim(fgets(STDIN));

print "Password: ";
system('stty -echo');
$password = password_hash(trim(fgets(STDIN)), PASSWORD_BCRYPT);
// Alternative: htpasswd -nB 

system('stty echo');
// add a new line since the users CR didn't echo
print "\n";

$upload = -1;
while ($upload < 0 or $upload > 1) {
    print "Upload (1 for yes, 0 for no): ";
    $input = trim(fgets(STDIN));
    if (is_numeric($input)) {
        $upload = intval($input);
    }
}

$view = -1;
while ($view < 0 or $view > 1) {
    print "View (1 for yes, 0 for no): ";
    $input = trim(fgets(STDIN));
    if (is_numeric($input)) {
        $view = intval($input);
    }
}

$query = <<<SQL
    INSERT INTO user (user, hash, upload, view) 
    VALUES ('$user', '$password', $upload, $view);
SQL;

print "$query\n\n";

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DB);

// Check connection
if ($conn->connect_error) {
    print 'MySQL connection failed: ' . $conn->connect_error . "\n\n";
    die();
}

$result = $conn->query($query);
if (!$result) {
    print 'MySQL INSERT error message: ' . $conn->error . "\n" . $query . "\n\n");
    die();
}

print "User added!\n\n";

