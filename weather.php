<?php
  require "header.php";
  require "chart.php";
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm">
        <h1 class="m-0 text-dark">Weather</h1>
      </div>
    </div>
  </div>
</div>

<!-- Main content -->
<div class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-md-12"> <!-- md-12 vs md??? -->
        <!-- Time selection card -->
        <div class="card card-primary">
          <div class="card-header">
            <h3 class="card-title">
              Select time range
            </h3>
          </div> <!-- .card-header -->
          <div class="card-body">
            <div class="input-group mb-3">
              <div class="input-group-prepend">
                <span class="input-group-text">
                  <i class="far fa-clock"></i>
                </span>
              </div>
              <input type="button" class="form-control pull-right" id="querytime" value="Click to select date and time range">
            </div>
          </div> <!-- .box-body -->
        </div> <!-- .box -->
      </div>
    </div>

    <?php
      newChart("temp", "Temperature");
      newChart("humidity", "Humidity");
      newChart("pressure", "Pressure");
    ?>

  </div> <!-- /.container-fluid -->
</div> <!-- /.content -->

<!-- ChartJS -->
<script src="plugins/moment/moment.min.js"></script>
<!-- <script src="plugins/chart.js/Chart.min.js"></script> -->
<script src="plugins/chart.js/Chart.js"></script>
<script src="plugins/daterangepicker/daterangepicker.js"></script>

<!-- page script -->
<script type="module">
  "use strict";

  import * as Graph from './graph.js';

  var graphs = { "temp": new Graph.Graph('line', 'temp', ['']),
                 "humidity": new Graph.Graph('line', 'humidity', ['']),
                 "pressure": new Graph.Graph('line', 'pressure', [''])
              };

  graphs['humidity'].options.scales.yAxes[0].ticks.beginAtZero = false;
  graphs['pressure'].options.scales.yAxes[0].ticks.beginAtZero = false;

  $(document).ready(function () {
    window.chartColors = Graph.makeDefaultGraphColours();

    for (let graphName in graphs) {
      let graph = graphs[graphName];

      let canvas = $(`#${graph.getElement()}`).get(0).getContext('2d');

      graph.chart = new Chart(canvas, {
        type: graph.type,
        options: graph.options
      });
    }

    $('#querytime').daterangepicker(Graph.makeDefaultTimePickerOptions());

    // Initial fetch
    const deltaSeconds_str = sessionStorage.getItem('weatherPreviousDeltaSeconds');

    let picker = $('#querytime').data('daterangepicker');
    let startDate = picker.startDate;
    let endDate = picker.endDate;
    if (deltaSeconds_str != null)
    {
      startDate = moment().subtract(deltaSeconds_str, 'seconds');
      endDate = moment();
    }
    fetchAndUpdate(startDate, endDate, picker.locale.format);
  });

  $("#querytime").on("apply.daterangepicker", function (ev, picker) {
    fetchAndUpdate(picker.startDate, picker.endDate, picker.locale.format);
  });

  function fetchAndUpdate(startDate, endDate, dateFormat) {
    sessionStorage.setItem('weatherPreviousDeltaSeconds', endDate.diff(startDate, 'seconds'));

    $('#querytime').val(startDate.format(dateFormat) + " - " + endDate.format(dateFormat) + " (" + Graph.deltaString(startDate, endDate) + ")");
    for (let graphName in graphs) {
      $(`#${graphs[graphName].getCardId()} .overlay`).show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);
    $.getJSON(`api_db.php?getGraphData3&sensor&from=${startUTC}&to=${endUTC}&types=0,1,2`,
      function (data) {
        let totalNum = 0;

        const typeToChart = { 0: graphs['temp'].chart, 1: graphs['humidity'].chart, 2: graphs['pressure'].chart };

        let devices = new Set();

        for (let graphName in graphs) {
          graphs[graphName].chart.indexToDevice = [];
        }

        let [period_s, unit] = Graph.getRawDataPeriod(endUTC - startUTC);

        $.each(data,
          function(deviceId, rec0) {

            if (deviceId === 'debug') {
              console.log(rec0);
              return;
            }

            devices.add(deviceId);

            $.each(rec0,
              function(type, rec1) {

                if (type > 2)
                {
                  return;
                }

                let chart = typeToChart[type];

                if (!chart.indexToDevice.includes(deviceId)) {
                  chart.indexToDevice.push(deviceId);
                }
                let deviceIndex = chart.indexToDevice.indexOf(deviceId);

                chart.data.datasets[deviceIndex] =
                {
                  label: 'Device ' + deviceId,
                  backgroundColor: Object.keys(window.chartColors)[deviceId - 1],
                  borderColor: Object.keys(window.chartColors)[deviceId - 1],
                  fill: false,
                  data: []
                };

                $.each(rec1,
                  function(timestamp_s, val) {
                    ++totalNum;
                    chart.data.datasets[deviceIndex].data.push({ x: new Date(Number(timestamp_s * 1000)), y: val});
                  }
                ); // $.each rec1
              }
            ); // $.each rec0
          }
        ); // $.each data

        console.log("Got " + totalNum + " records");

        let deviceRequests = [];

        // Get locations
        for (const deviceId of devices) {
          deviceRequests.push($.getJSON(`api_db.php?getLocation&deviceId=${deviceId}&endTS=${endUTC}`));
        }
        $.when.apply($, deviceRequests).done(function() {
          let responses = deviceRequests.length === 1 ? [arguments] : arguments;
          let deviceToLocation = new Map();
          for (let resultIndex in responses) {
            let locationData = responses[resultIndex][0];
            deviceToLocation.set(locationData[0]['deviceId'], locationData[0]['location']);
          }

          // Get device names
          deviceRequests = [];
          for (const deviceId of devices) {
            deviceRequests.push($.getJSON("api_db.php?getDeviceDesc&deviceId=" + deviceId));
          }

          $.when.apply($, deviceRequests).done(function() {
            let responses = deviceRequests.length === 1 ? [arguments] : arguments;
            for (let resultIndex in responses) {
              let deviceData = responses[resultIndex][0];
              for (let graphName in graphs) {
                let graph = graphs[graphName];
                let deviceId = deviceData['deviceId'];
                let deviceIndex = graph.chart.indexToDevice.indexOf(deviceId);
                if (graph.chart.data.datasets[deviceIndex] !== undefined) {
                  graph.chart.data.datasets[deviceIndex].label = deviceData['friendlyName'] + ' ' + deviceToLocation.get(deviceId);
                }
              }
            }
            for (let graphName in graphs) {
              let graph = graphs[graphName];
              graph.chart.options.scales.xAxes[0].time.unit = unit;
              graph.chart.options.scales.xAxes[0].time.stepSize = period_s / Graph.getSeconds(unit);
              graph.chart.update();
              $(`#${graph.getCardId()} .overlay`).hide();
            }
          });
        });

      } // Json handler
    );
  }
</script>

<?php
  require "footer.php"
?>
