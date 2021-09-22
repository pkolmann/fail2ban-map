#!/usr/bin/php
<?php
$appdir = dirname(__FILE__);

# Script used to import data from fail2ban logfiles

require $appdir.'/config.php';
require $appdir.'/vendor/autoload.php';
use GeoIp2\Database\Reader;

$options = getopt("f:hs:");

if (!array_key_exists('f', $options)
    || !array_key_exists('s', $options)
    || array_key_exists('h', $options)
) {
    print "Usage:\n";
    print $argv[0]." -f File to import -s serverId\n\n";
    die();
}


try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_DB);
} catch (Exception $e) {
        die("Can't connect to DB!\n\n");
}

$server = $options['s'];

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

$data = file_get_contents($options['f']);
if (!$data) {
    die("Error reading ".$options['f']."\n");
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
$agent = 'Import Script';

$query = <<<SQL
    INSERT INTO log SET
        time = FROM_UNIXTIME(?),
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

    $insert = $mysqli->prepare($query);
    $insert->bind_param("iisssssssssdds", $time, $serverId, $service, $agent, $ip, $hostname,
        $countryISO, $country, $subdiv, $city, $postal, $lat, $long, $network);



foreach (explode("\n", $data) as $line) {
    if (strlen($line) < 10) continue;
    if (strpos($line, '] Ban ') === false) continue;

    $time = null;
    $service = null;
    $ip = null;
    $hostname = null;
    $countryISO = null;
    $country = null;
    $subdiv = null;
    $city = null;
    $postal = null;
    $lat = null;
    $long = null;
    $network = null;

    $date = substr($line, 0, strpos($line, ','));
    $srvStart = strrpos($line, '[') + 1;
    $srvEnd = strrpos($line, ']');
    $service = substr($line, $srvStart, ($srvEnd - $srvStart));
    if ($service != 'sshd') continue;

    $ip = substr($line, strpos($line, 'Ban ') + 4);
    print "   $date - $service - $ip\n";

    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        try {
            $hostname = gethostbyaddr($ip);
        } catch (Exception $e) {
            # fail silently
        }
    }

    $time = strtotime(substr($date, 0, 19));

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

    try {
        $res = $insert->execute();
    } catch (Exception $e) {
        print_r($e);
        die("Failed to insert log!\n\n");
    }

    if (!$res) {
       die("DB query failed!\n\n");
    }
}


