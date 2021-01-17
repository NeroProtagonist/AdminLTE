<?php

header('Content-type: application/json');

$data = array();

$dbpassFile = fopen('dbpass', 'r') or die('Unable to open file');
$dbpass = fgets($dbpassFile);
fclose($dbpassFile);
$logConnection = new mysqli('aardy', 'logger', $dbpass, 'sensorLogs');
if ($logConnection->connect_errno) {
    die("Failed to connect to server: (" . $logConnection->connect_errno . ") " . $logConnection->connect_error);
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
    while ($row = $res->fetch_array(MYSQLI_NUM)) {    // TODO: Don't necessarily need fetch_array(_BOTH).
        $time = DateTime::createFromFormat('Y-m-d H:i:s', $row[0], new DateTimeZone("UTC"));
        $timestamp_s = $time->format('U');
        $returnedData[$timestamp_s] = array_slice($row, 1);
    }

    $data = $returnedData;
}

echo json_encode($data);
