<?php
$appdir = dirname(__FILE__, 2);
header('Content-type: text/plain');

require $appdir.'/config.php';
require $appdir.'/vendor/autoload.php';
use GeoIp2\Database\Reader;


try {
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DB);
} catch (Exception $e) {
    die("Can't connect to DB!\n\n");
}

if (!array_key_exists('REMOTE_USER', $_SERVER)) {
    die('no servername given!');
}

$server = $_SERVER['REMOTE_USER'];

$query = <<<SQL
    SELECT user_id
    FROM user
    WHERE user = ?
SQL;

$getServerId = $mysqli->prepare($query);
$getServerId->bind_param("s", $server);
$res = $getServerId->execute(); 

if (!$res) {
    die("DB query failed!\n\n");
}

$getServerId->bind_result($serverId);
$getServerId->fetch();
$getServerId->close();

if (is_null($serverId)) {
    print "No Server found!\n\n";
    die;
}

if (!array_key_exists('ip', $_POST)) {
    die('no IP Address given!');
}
$ip = $_POST['ip'];
$hostname = null;
if (filter_var($ip, FILTER_VALIDATE_IP)) {
    try {
        $hostname = gethostbyaddr($ip);
    } catch (Exception $e) {
        # fail silently
    }
}
if ($hostname == $ip) {
    $hostname = null;
}

if (!array_key_exists('service', $_POST)) {
    die('no service given!');
}
$service = $_POST['service'];

if (!array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
    die('no agent given!');
}
$agent = $_SERVER['HTTP_USER_AGENT'];

$time = null;
if (array_key_exists('time', $_POST)) {
    $time = strtotime(substr($_POST['time'], 0, 19));
}



$reader = new Reader('/usr/share/GeoIP/GeoLite2-City.mmdb');

$countryISO = null;
$country = null;
$subdiv = null;
$city = null;
$postal = null;
$lat = null;
$long = null;
$network = null;
try {
    $record = $reader->city($ip);

    $countryISO = $record->country->isoCode; // 'US'
    $country = $record->country->name; // 'United States'
    $subdiv = $record->mostSpecificSubdivision->name; // 'Minnesota'
    $city = $record->city->name; // 'Minneapolis'
    $postal = $record->postal->code; // '55455'
    $lat = $record->location->latitude; // 44.9733
    $long = $record->location->longitude; // -93.2323
    $network = $record->traits->network; // '128.101.101.101/32'
} catch (Exception $e) {
    # Ignore fail
}

$query = <<<SQL
    INSERT INTO log SET
        user_id = ?,
        service = ?,
        agent = ?,
        ip = ?,
        hostname = ?,
        countryISO = ?,
        country = ?,
        subdiv = ?,
        city = ?,
        postal = ?,
        lat = ?,
        `long` = ?,
        network = ?
SQL;

if (!is_null($time)) {
    $query .= ', time = FROM_UNIXTIME(?)';
}

$insert = $mysqli->prepare($query);
if (is_null($time)) {
$insert->bind_param("isssssssssdds", $serverId, $service, $agent, $ip, $hostname,
    $countryISO, $country, $subdiv, $city, $postal, $lat, $long, $network);
} else {
    $insert->bind_param("isssssssssddsi", $serverId, $service, $agent, $ip, $hostname,
        $countryISO, $country, $subdiv, $city, $postal, $lat, $long, $network, $time);
}
try {
    $res = $insert->execute(); 
} catch (Exception $e) {
    print_r($e);
    die("Failed to insert log!\n\n");
}

if (!$res) {
    die("DB query failed!\n\n");
}


