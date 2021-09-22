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

if (!array_key_exists('id', $_GET)) {
    $error = [
        'error' => true,
        'errorMsg' => 'No Server-Id found!'
    ];
    print json_encode($error);
    die();
}

$server = intval($_GET['id']);

$query = <<<SQL
    SELECT log_id, time, UNIX_TIMESTAMP(time) as timestamp,
        service, agent, ip, hostname, countryISO, country, 
        subdiv, city, postal, lat, `long`, network
    FROM log
    WHERE user_id = ?
        AND time > DATE(NOW()-INTERVAL 1 MONTH)
    ORDER BY time
SQL;

$getLog = $mysqli->prepare($query);
$getLog->bind_param("i", $server);
$res = $getLog->execute(); 

if (!$res) {
    die("DB query failed!\n\n");
}

$res = $getLog->get_result();

print '{"type": "FeatureCollection","features": [';
$firstLine = true;
while ($row = $res->fetch_assoc()) {
    if (is_null($row['long']) || is_null($row['lat'])) {
        continue;
    }

    if ($firstLine) {
        $firstLine = false;
    } else {
        print ",\n";
    }
    print '{"type": "Feature","geometry": {"type": "Point", ';
    print '"coordinates": ['.$row['long'].', '.$row['lat'].']';
    print '}, "properties": {';
    print '    "time": "'.$row['time'].'",';
    print '    "timestamp": "'.$row['timestamp'].'",';
    print '    "service": "'.$row['service'].'",';
    print '    "agent": "'.$row['agent'].'",';
    print '    "ip": "'.$row['ip'].'",';
    if (!is_null($row['hostname'])) {
        print '    "hostname": "'.$row['hostname'].'",';
    }
    print '    "countryISO": "'.$row['countryISO'].'",';
    print '    "country": "'.$row['country'].'",';
    print '    "subdiv": "'.$row['subdiv'].'",';
    print '    "city": "'.$row['city'].'",';
    print '    "postal": "'.$row['postal'].'",';
    print '    "network": "'.$row['network'].'"';
    print '},';
    print '"id": '.$row['log_id'];
    print '}';
}
$getLog->close();
print ']}';



