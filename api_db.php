<?php

$dbpassFile = fopen('dbpass', 'r') or die('Unable to open file');
$sensorDBPass = fgets($dbpassFile);
fclose($dbpassFile);
//$logConnection = new mysqli('frambo', 'logger', $sensorDBPass, 'sensorLogs');
$logConnection = new mysqli('furion', 'logViewer', $sensorDBPass, 'sensorLogs');
if ($logConnection->connect_errno) {
    die("Failed to connect to server: (" . $logConnection->connect_errno . ") " . $logConnection->connect_error);
}

function parseCommaGetParam($param)
{
    $values = array();
    if (isset($_GET[$param])) {
        $split = preg_split('/,/', $_GET[$param]);
        foreach ($split as $val) {
            if ($val != '' && !is_numeric($val)) {
                die("{$val} not numeric");
            }
            if ($val != '') {
                $values[] = $val;
            }
        }
    }

    return $values;
}

function makeSQLIn($column, $values) {
    $sql = "{$column} IN ({$values[0]}";
    for ($n = 1; $n < sizeof($values); $n++) {
        $sql .= ",{$values[$n]}";
    }
    $sql .= ")";
    return $sql;
}

if (isset($_GET['getDeviceIds'])) {
    $logConnection->select_db("sensorLogs");
    $sql = "SELECT deviceId FROM devices";

    $types = parseCommaGetParam('types');
    if (sizeof($types) != 0) {
        $sql .= " WHERE FIND_IN_SET('$types[0]', dataTypes) > 0";
        for ($n = 1; $n < sizeof($types); $n++) {
            $sql .= " OR FIND_IN_SET('$types[$n]', dataTypes) > 0";
        }
    }

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

if (isset($_GET['getSensorDeviceTypes'])) {
    if (!isset($_GET['deviceId'])) {
        die('deviceId not set');
    }

    $deviceId = $_GET['deviceId'];

    $logConnection->select_db("sensorLogs");
    $sql = "SELECT dataTypes FROM devices WHERE deviceId = {$deviceId}";
    $res = $logConnection->query($sql);
    $dataTypes = explode(',', $res->fetch_array()[0]);
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($dataTypes);
    return;
}

if (isset($_GET['getLocation'])) {
    if (!isset($_GET['deviceId'])) {
        die('deviceId not set');
    }

    $deviceId = $_GET['deviceId'];
    if (isset($_GET['endTS'])) {
        $endTime = new DateTime("@{$_GET['endTS']}");
        $sql = "SELECT deviceId,location,startTS FROM locations WHERE deviceId = {$deviceId} AND startTS < {$endTime->format('U')} ORDER BY startTS DESC";
    } else {
        $sql = "SELECT deviceId,location FROM locations WHERE deviceId = {$deviceId} ORDER BY startTS DESC LIMIT 1";
    }

    $logConnection->select_db("sensorLogs");

    $res = $logConnection->query($sql);
    $data = array();
    while ($row = $res->fetch_assoc()) {
        $data[] = $row;
    }
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($data);
    return;
}

if (isset($_GET['getLastValues']) && isset($_GET['sensor'])) {
    if (!isset($_GET['deviceId'])) {
        die('deviceId not set');
    }

    $deviceId = $_GET['deviceId'];

    $ignore = '';
    if (isset($_GET['ignoreOlderThan'])) {
        $ignore = ' AND ts >= ' . $_GET['ignoreOlderThan'];
    }

    if (isset($_GET['movingAverage'])) {
        $offset = $_GET['movingAverage'];
        if (!is_numeric($offset)) {
            die("_GET['movingAverage'] '$offset' not numeric");
        }
    }

    $data = array();

    // Get types for device
    $logConnection->select_db("sensorLogs");
    $sql = "SELECT dataTypes FROM devices WHERE deviceId = {$deviceId}";
    $res = $logConnection->query($sql);
    $dataTypes = explode(',', $res->fetch_array()[0]);
    foreach($dataTypes as $type)
    {
        if (!isset($offset)) {
            $sql = "SELECT ts, type, value
                    FROM log
                    WHERE deviceId = {$deviceId}
                        AND type = {$type}
                        {$ignore}
                    ORDER BY ts DESC LIMIT 1";
        } else {
            $sql = "SELECT MAX(ts) AS ts, type, AVG(value) AS value
                    FROM log
                    WHERE deviceId = {$deviceId}
                        AND type = {$type}
                        {$ignore}
                        AND ts >= (
                                    SELECT ts - 120
                                    FROM log
                                    WHERE deviceId = {$deviceId}
                                        AND type = {$type}
                                    ORDER BY ts DESC LIMIT 1
                                    )";
        }
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

if (isset($_GET['getLastValues']) && isset($_GET['meter'])) {
    $stats = parseCommaGetParam('stats');

    // Fetch all stats
    $logConnection->select_db("meterLogs");
    //$sql = "SELECT l.stat, l.dateTime, l.value, stats.unit, stats.description FROM log l INNER JOIN (SELECT MAX(recordId) rec FROM log GROUP BY stat) recent ON l.recordId = recent.rec INNER JOIN stats ON l.stat = stats.stat";
    $where = "";
    if (sizeof($stats) != 0) {
        $where = "WHERE " . makeSQLIn('stat', $stats);
    }
    $sql = "SELECT l.stat, l.ts, l.value, stats.unit, stats.description FROM log l
                    INNER JOIN (SELECT MAX(recent.ts) ts FROM
                            (SELECT ts, stat FROM log ${where} ORDER BY ts DESC LIMIT 1000) recent GROUP BY stat) recent ON l.ts = recent.ts
                    INNER JOIN stats ON l.stat = stats.stat";
    $res = $logConnection->query($sql);

    $data = array();
    while ($row = $res->fetch_array(MYSQLI_ASSOC)) {
        $data[$row['stat']] = $row;
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

    $logConnection->select_db("sensorLogs");
    $sql = "SELECT deviceId, name, friendlyName, sensorName FROM devices WHERE deviceId = $deviceId";
    $res = $logConnection->query($sql);

    $data = $res->fetch_assoc();
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($data);
    return;
}

function getQueryTimespan()
{
    $fromTime = new DateTime("@{$_GET['from']}");
    $toTime = new DateTime("@{$_GET['to']}");
    return array($fromTime, $toTime);
}

function getSQLDateTimeLimit()
{
    // Time limit
    $limit = '';

    list($fromTime, $toTime) = getQueryTimespan();

    if (isset($_GET['from'])) {
        $limit .= ' AND dateTime >= "' . $fromTime->format('Y-m-d H:i:s') . '"';
    }
    if (isset($_GET["to"])) {
        $limit .= ' AND dateTime <= "' . $toTime->format('Y-m-d H:i:s') . '"';
    }

    // Remove first AND
    $limit = preg_replace('/^ AND/', '', $limit);

    return $limit;
}

function getSQLTimestampLimit($fromTimeOffset_s = 0, $toTimeOffset_s = 0)
{
    // Time limit
    $limit = '';

    list($fromTime, $toTime) = getQueryTimespan();
    $fromTime->sub(new DateInterval("PT${fromTimeOffset_s}S"));
    $toTime->sub(new DateInterval("PT${toTimeOffset_s}S"));

    if (isset($_GET['from'])) {
        $limit .= " AND ts >= {$fromTime->format('U')}";
    }
    if (isset($_GET["to"])) {
        $limit .= " AND ts <= {$toTime->format('U')}";
    }

    // Remove first AND
    $limit = preg_replace('/^ AND/', '', $limit);

    return $limit;
}

function sqlDateTimeToTimestamp($dateTime)
{
    $time = DateTime::createFromFormat('Y-m-d H:i:s', $dateTime, new DateTimeZone("UTC"));
    return $time->format('U');
}

function addDebugData(&$dataArray, $name, $value)
{
    $dataArray['debug'][$name] = $value;
}

if (isset($_GET['getGraphData2']) && isset($_GET['weather'])) {

    $timeLimit = getSQLDateTimeLimit();

    $returnedData = array();

    // Get number of samples in devices and types
    $logConnection->select_db("sensorLogs");
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
            $timestamp_s = sqlDateTimeToTimestamp($sampleRow[0]);
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

if (isset($_GET['getGraphData3']) && isset($_GET['sensor'])) {

    # Requires from+to
    if (!isset($_GET['from']) || !isset($_GET['to'])) {
        die('Needs from and to');
    }

    $timeLimit = getSQLTimestampLimit();

    $logConnection->select_db("sensorLogs");
    $sql = "SELECT ts, deviceId, type, value FROM log WHERE";

    $types = parseCommaGetParam('types');
    $sqlTypes = "";
    if (sizeof($types) != 0) {
        $sqlTypes = makeSQLIn('type', $types);
        $sql .= " $sqlTypes AND";
    }

    if ($timeLimit != '') {
        $sql .= " $timeLimit AND";
    }

    $sql = preg_replace('/AND$/', '', $sql);

    list($fromTime, $toTime) = getQueryTimespan();
    $delta_s = abs($fromTime->getTimestamp() - $toTime->getTimestamp());
    $numSamples = 50;
    $sql .= " GROUP BY ts DIV ($delta_s / $numSamples), deviceId, type";

    if (isset($_GET['movingAverage'])) {
        $offset = $_GET['movingAverage'];
        if (!is_numeric($offset)) {
            die("_GET['movingAverage'] '$offset' not numeric");
        }
        $extendedTimeLimit = getSQLTimestampLimit($offset);
        $sql = "SELECT a.ts, a.deviceId, a.type, AVG(b.value)
                FROM (
                    $sql
                    ) AS a
                    JOIN (
                        SELECT ts, deviceId, type, value
                        FROM log
                        WHERE $sqlTypes
                            AND $extendedTimeLimit
                        ORDER BY deviceId, type, ts
                    ) AS b
                    ON a.deviceId = b.deviceId
                        AND a.type = b.type
                        AND CAST(a.ts AS SIGNED) - CAST(b.ts AS SIGNED) BETWEEN 0 AND $offset
                GROUP BY deviceId, type, a.ts";
        $sql = preg_replace('/\n/', '', $sql);
    }

    $res = $logConnection->query($sql);
    if (!$res) {
        die("Table query failed: (" . $logConnection->errno . ") " . $logConnection->error);
    }

    $returnedData = array();
    while ($row = $res->fetch_array(MYSQLI_NUM)) {
        $timestamp_s = $row[0];
        $deviceId = $row[1];
        $type = $row[2];
        $value = $row[3];
        // { deviceId => type => timestamp => value }
        $returnedData[$deviceId][$type][$timestamp_s] = $value;
    }
    $res->free_result();

    addDebugData($returnedData, 'query', $sql);

    header('Content-type: application/json');
    echo json_encode($returnedData);
    return;
}

if (isset($_GET['getGraphData']) && isset($_GET['weather'])) {

    $timeLimit = getSQLDateTimeLimit();

    $logConnection->select_db("sensorLogs");
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
        $timestamp_s = sqlDateTimeToTimestamp($row[0]);
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

if (isset($_GET['getGraphData']) && isset($_GET['solar'])) {
    $timeLimit = getSQLTimestampLimit();

    $logConnection->select_db("solarLogs");
    $sql = "SELECT ts, current_w, lifetime_wh FROM log";
    if ($timeLimit != '') {
        $sql .= " WHERE $timeLimit";
    }

    $res = $logConnection->query($sql);
    if (!$res) {
        die("Table query failed: (" . $logConnection->errno . ") " . $logConnection->error);
    }

    $returnedData = array();
    $returnedData = $res->fetch_all(MYSQLI_ASSOC);
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($returnedData);
    return;
}

if (isset($_GET['getGraphData']) && isset($_GET['meter'])) {

    if (!isset($_GET['from']) || !isset($_GET['to']) || !isset($_GET['period_s'])) {
        die('Needs period');
    }

    // TODO: Perhaps SQL can sum up energy for time period

    $timeLimit = getSQLTimestampLimit();

    $logConnection->select_db('meterLogs');
    $sql = "SELECT ts, stat, value FROM log WHERE";
    if ($timeLimit != '') {
        $sql .= " $timeLimit ";
    }

    list($fromTime, $toTime) = getQueryTimespan();
    $interval_s = $_GET['period_s'];
    $sql .= "GROUP BY ts DIV $interval_s, stat ORDER BY stat, ts";

    $res = $logConnection->query($sql);
    if (!$res) {
        die("Table query failed: (" . $logConnection->errno . ") " . $logConnection->error);
    }

    $returnedData = $res->fetch_all(MYSQLI_ASSOC);
    $res->free_result();

    //addDebugData($returnedData, 'query', $sql);

    header('Content-type: application/json');
    echo json_encode($returnedData);
    return;
}

// Whoa - Calculate average power over 15 second window for last 10 minutes of data
// SELECT *, AVG(value) OVER (ORDER BY unix_timestamp(dateTime) RANGE BETWEEN 15 PRECEDING AND CURRENT ROW ) FROM (SELECT *, UNIX_TIMESTAMP(dateTime) FROM log WHERE dateTime > UTC_TIMESTAMP() - INTERVAL 10 MINUTE AND stat = 9) recent

if (isset($_GET['getPrices']) && isset($_GET['meter'])) {

    $logConnection->select_db('meterLogs');
    $sql = "SELECT * FROM prices";
    $res = $logConnection->query($sql);
    if (!$res) {
        die("Table query failed: (" . $logConnection->errno . ") " . $logConnection->error);
    }

    $returnedData = $res->fetch_all(MYSQLI_ASSOC);
    $res->free_result();

    header('Content-type: application/json');
    echo json_encode($returnedData);
    return;
}

die("no command?");
