#!/usr/bin/php
<?php

print "User / Servername: ";
$user = trim(fgets(STDIN));

print "Password: ";
system('stty -echo');
$password = password_hash(trim(fgets(STDIN)), PASSWORD_BCRYPT);
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
    print "Upload (1 for yes, 0 for no): ";
    $input = trim(fgets(STDIN));
    if (is_numeric($input)) {
        $view = intval($input);
    }
}

$query = <<<SQL
    INSERT INTO fail2ban.user (user, hash, upload, view) 
    VALUES ($user, $password, $upload, $view);
SQL;

print "$query\n\n";
