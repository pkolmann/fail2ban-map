<?php
$appdir = dirname(__FILE__, 4);
header('Content-type: application/json');

require $appdir.'/config.php';
require $appdir.'/vendor/autoload.php';


try {
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli(DB_HOST, DB_RO_USER, DB_RO_PASS, DB_DB);
} catch (Exception $e) {
    die("Can't connect to DB!\n\n");
}

$query = <<<SQL
    SELECT user_id, user
    FROM user
    WHERE user_id IN (
        SELECT DISTINCT user_id FROM log
    );
SQL;

$getServerId = $mysqli->prepare($query);
$res = $getServerId->execute(); 

if (!$res) {
    die("DB query failed!\n\n");
}

$res = $getServerId->get_result();

$servers = [];
while ($row = $res->fetch_assoc()) {
    $servers[$row['user_id']] = $row['user'];
}
$getServerId->close();

print json_encode($servers);


