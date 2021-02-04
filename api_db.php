<?php

$dbpassFile = fopen('dbpass', 'r') or die('Unable to open file');
$dbpass = fgets($dbpassFile);
fclose($dbpassFile);
//$logConnection = new mysqli('frambo', 'logger', $dbpass, 'sensorLogs');
$logConnection = new mysqli('furion', 'logViewer', $dbpass, 'sensorLogs');
if ($logConnection->connect_errno) {
    die("Failed to connect to server: (" . $logConnection->connect_errno . ") " . $logConnection->connect_error);
}

if (isset($_GET['getDeviceIds'])) {
    $sql = "SELECT deviceId FROM devices";
    $res = $logConnection->query($sql);
    $data = array();
    while ($row = $res->fetch_assoc()) {
        $data[] = (int)$row['deviceId'];
    }
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($data);
    return;
}

if (isset($_GET['getLastValues'])) {
    if (!isset($_GET['deviceId'])) {
        die('deviceId not set');
    }

    $deviceId = $_GET['deviceId'];

    $data = array();

    // Get types for device
    $sql = "SELECT dataTypes FROM devices WHERE deviceId = {$deviceId}";
    $res = $logConnection->query($sql);
    $dataTypes = explode(',', $res->fetch_array()[0]);
    foreach($dataTypes as $type)
    {
        $sql = "SELECT dateTime, value, type FROM log WHERE deviceId = {$deviceId} AND type = {$type} ORDER BY recordId DESC LIMIT 1";
        $res2 = $logConnection->query($sql);
        while ($row2 = $res2->fetch_assoc()) {
            $data[] = $row2;
        }
        $res2->free_result();
    }
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($data);
    return;
}

if (isset($_GET['getDeviceDesc'])) {
    if (!isset($_GET['deviceId'])) {
        die('deviceId not set');
    }

    $deviceId = $_GET['deviceId'];

    $sql = "SELECT deviceId, name, friendlyName, sensorName FROM devices WHERE deviceId = $deviceId";
    $res = $logConnection->query($sql);

    $data = $res->fetch_assoc();
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($data);
    return;
}

function getSQLTimeLimit()
{
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

    return $limit;
}

function sqlToTimestamp($dateTime)
{
    $time = DateTime::createFromFormat('Y-m-d H:i:s', $dateTime, new DateTimeZone("UTC"));
    return $time->format('U');
}

if (isset($_GET['getGraphData2']) && isset($_GET['weather'])) {

    $timeLimit = getSQLTimeLimit();

    $returnedData = array();

    // Get number of samples in devices and types
    $sql = "SELECT COUNT(*) AS 'numSamples', deviceId, type FROM log WHERE $timeLimit GROUP BY deviceId, type";
    $res = $logConnection->query($sql);
    while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
        $numSamples = $row['numSamples'];
        $period = max(round($numSamples / 200), 1); // Need about 200 samples
        $deviceId = $row['deviceId'];
        $type = $row['type'];
        $dataSql = "
            SELECT dateTime, value
            FROM (
                SELECT @row := @row + 1 AS rowNum, dateTime, value
                FROM (SELECT @row := -1) r, log
                WHERE deviceId = $deviceId AND type = $type AND $timeLimit
                ) ranked
            WHERE rowNum % $period = 0
                ";

        $dataRes = $logConnection->query($dataSql);
        while ($sampleRow = $dataRes->fetch_array(MYSQLI_NUM)) {
            $timestamp_s = sqlToTimestamp($sampleRow[0]);
            $value = $sampleRow[1];
            // { deviceId => type => timestamp => value }
            $returnedData[$deviceId][$type][$timestamp_s] = $value;
        }
        $dataRes->free_result();
    }
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($returnedData);
    return;
}

if (isset($_GET['getGraphData']) && isset($_GET['weather'])) {

    $timeLimit = getSQLTimeLimit();

    $sql = "SELECT dateTime, deviceId, value, type FROM log";
    if ($timeLimit != '') {
        $sql .= " WHERE $timeLimit";
    }

    $res = $logConnection->query($sql);
    if (!$res) {
        die("Table query failed: (" . $logConnection->errno . ") " . $logConnection->error);
    }

    $returnedData = array();
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
        $timestamp_s = sqlToTimestamp($row[0]);
        $deviceId = $row[1];
        $value = $row[2];
        $type = $row[3];
        // { deviceId => type => timestamp => value }
        $returnedData[$deviceId][$type][$timestamp_s] = $value;
    }
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($returnedData);
    return;
}

die("no command?");
