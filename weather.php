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
      newChart("relHumidity", "Relative Humidity");
      newChart("absHumidity", "Absolute Humidity");
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
                 "relHumidity": new Graph.Graph('line', 'relHumidity', ['']),
                 "absHumidity": new Graph.Graph('line', 'absHumidity', ['']),
                 "pressure": new Graph.Graph('line', 'pressure', [''])
              };

  graphs['relHumidity'].options.scales.yAxes[0].ticks.beginAtZero = false;
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

        const typeToChart = { 0: graphs['temp'].chart, 1: graphs['relHumidity'].chart, 2: graphs['pressure'].chart };

        let devices = new Set();

        for (let graphName in graphs) {
          graphs[graphName].chart.indexToDevice = [];
        }

        let [period_s, unit] = Graph.getRawDataPeriod(endUTC - startUTC);

        function initDataset(deviceId) {
          return {
            label: 'Device ' + deviceId,
            backgroundColor: Object.keys(window.chartColors)[deviceId - 1],
            borderColor: Object.keys(window.chartColors)[deviceId - 1],
            fill: false,
            data: []
          };
        }

        $.each(data,
          function(deviceId, rec0) {

            if (deviceId === 'debug') {
              console.log(rec0);
              return;
            }

            devices.add(deviceId);

            let absHumidityData = new Map();

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

                chart.data.datasets[deviceIndex] = initDataset(deviceId);

                $.each(rec1,
                  function(timestamp_s, val) {
                    ++totalNum;
                    chart.data.datasets[deviceIndex].data.push({ x: new Date(Number(timestamp_s * 1000)), y: val});
                    if (!absHumidityData.has(timestamp_s)) {
                      absHumidityData.set(timestamp_s, { temp: null, humidity: null });
                    }
                    if (type === 0) {
                      absHumidityData.get(timestamp_s).temp = val;
                    } else if (type === 1) {
                      absHumidityData.get(timestamp_s).humidity = val;
                    }
                  }
                ); // $.each rec1 (timestamp + val)
              }
            ); // $.each rec0 (type)

            let ahChart = graphs['absHumidity'].chart;
            if (!ahChart.indexToDevice.includes(deviceId)) {
              ahChart.indexToDevice.push(deviceId);
            }
            const deviceIndex = ahChart.indexToDevice.indexOf(deviceId);
            ahChart.data.datasets[deviceIndex] = initDataset(deviceId);

            function degToKelvin(deg) {
              return deg + 273.15;
            }

            function getAbsHumidity(temp, relativeHumidity) {
              const T = degToKelvin(temp);
              const a1 = -7.85951783;
              const a2 = 1.84408259;
              const a3 = -11.7866497;
              const a4 = 22.6807411;
              const a5 = -15.9618719;
              const a6 = 1.80122502;
              const Tc = 647.096; // Kelvin
              const Pc = 22.064e6; // Pa
              const t = 1 - (T / Tc);
              const Ps = Pc * Math.exp((Tc / T) * (a1 * t + a2 * Math.pow(t, 1.5) + a3 * Math.pow(t, 3) + a4 * Math.pow(t, 3.5) + a5 * Math.pow(t, 4) + a6 * Math.pow(t, 7.5)));
              const Pa = Ps * (relativeHumidity / 100);
              const Rw = 461.5;
              const hA = Pa / (Rw * T);
              return hA * 1000; // to g/m^3
            }

            for (let [ts, data] of absHumidityData) {
              if (data.temp != null && data.humidity != null) {
                ahChart.data.datasets[deviceIndex].data.push({ x: new Date(ts * 1000), y: getAbsHumidity(Number(data.temp), Number(data.humidity))});
              }
            }
          }
        ); // $.each data (deviceId)

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
