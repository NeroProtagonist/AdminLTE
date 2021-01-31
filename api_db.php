<?php

$data = array();

$dbpassFile = fopen('dbpass', 'r') or die('Unable to open file');
$dbpass = fgets($dbpassFile);
fclose($dbpassFile);
//$logConnection = new mysqli('frambo', 'logger', $dbpass, 'sensorLogs');
$logConnection = new mysqli('furion', 'logViewer', $dbpass, 'sensorLogs');
if ($logConnection->connect_errno) {
    die("Failed to connect to server: (" . $logConnection->connect_errno . ") " . $logConnection->connect_error);
}

if(isset($_GET['getDeviceDesc'])) {
    if (!isset($_GET['deviceId'])) {
        die('deviceId not set');
    }

    $deviceId = $_GET['deviceId'];

    $sql = "SELECT name, friendlyName, sensorName FROM devices WHERE deviceId = $deviceId";
    $res = $logConnection->query($sql);

    $returnedData = $res->fetch_assoc();

    $data = $returnedData;
    header('Content-type: application/json');
    echo json_encode($data);
    return;
}

if(isset($_GET['getGraphData']) && isset($_GET['weather'])) {

    // TODO: Filter by device-id
    // TODO: Filter by type

    // Time limit
    $limit = '';

    if (isset($_GET['from'])) {
        $fromTime = new DateTime("@{$_GET['from']}");
        $limit .= ' AND dateTime >= "' . $fromTime->format('Y-m-d H:i:s') . '"';
    }
    if (isset($_GET["to"])) {
        $toTime = new DateTime("@{$_GET['to']}");
        $limit .= ' AND dateTime <= "' . $toTime->format('Y-m-d H:i:s') . '"';
    }

    // Remove first AND
    $limit = preg_replace('/^ AND/', '', $limit);

    $sql = "SELECT dateTime, deviceId, value, type FROM log";
    if ($limit != '') {
        $sql .= " WHERE $limit";
    }

    $res = $logConnection->query($sql);
    if (!$res) {
        die("Table query failed: (" . $logConnection->errno . ") " . $logConnection->error);
    }

    $returnedData = array();
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
        $time = DateTime::createFromFormat('Y-m-d H:i:s', $row[0], new DateTimeZone("UTC"));
        $timestamp_s = $time->format('U');
        $deviceId = $row[1];
        $value = $row[2];
        $type = $row[3];
        // { deviceId => type => timestamp => value }
        $returnedData[$deviceId][$type][$timestamp_s] = $value;
    }

    $data = $returnedData;
    header('Content-type: application/json');
    echo json_encode($data);
    return;
}
