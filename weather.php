<?php
  require "header.php"
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

    <div class="row">
      <div class="col-md">
        <!-- Main chart -->
        <div class="card card-primary" id="temp-graph">
          <div class="card-header">
            <h3 class="card-title">Temperature</h3>
              <div class="card-tools"> <!-- TODO: Probably unneeded -->
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                </button>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="tempGraphElement" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div> <!-- /.card-body -->
          <div class="overlay">
            <i class="fas fa-2x fa-sync-alt fa-spin"></i>
          </div> <!-- /.overlay -->
        </div> <!-- /.card -->
      </div>
    </div> <!-- row -->

    <div class="row">
      <div class="col-md">
        <!-- Main chart -->
        <div class="card card-primary" id="humidity-graph">
          <div class="card-header">
            <h3 class="card-title">Humidity</h3>
              <div class="card-tools"> <!-- TODO: Probably unneeded -->
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                </button>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="humidityGraph" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div> <!-- /.card-body -->
          <div class="overlay">
            <i class="fas fa-2x fa-sync-alt fa-spin"></i>
          </div> <!-- /.overlay -->
        </div> <!-- /.card -->
      </div>
    </div> <!-- row -->

    <div class="row">
      <div class="col-md">
        <!-- Main chart -->
        <div class="card card-primary" id="pressure-graph">
          <div class="card-header">
            <h3 class="card-title">Pressure</h3>
              <div class="card-tools"> <!-- TODO: Probably unneeded -->
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                </button>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="pressureGraph" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div> <!-- /.card-body -->
          <div class="overlay">
            <i class="fas fa-2x fa-sync-alt fa-spin"></i>
          </div> <!-- /.overlay -->
        </div> <!-- /.card -->
      </div>
    </div> <!-- row -->

  </div><!-- /.container-fluid -->
</div>
<!-- /.content -->
</div>

<!-- ChartJS -->
<script src="plugins/moment/moment.min.js"></script>
<script src="plugins/chart.js/Chart.min.js"></script>
<!--<script src="plugins/chart.js/Chart.js"></script>-->
<script src="plugins/daterangepicker/daterangepicker.js"></script>
<!-- page script -->
<script type="module">
  "use strict";

  import { makeDefaultGraphOptions } from './graph.js';
  import { deltaString } from './graph.js';
  import { makeDefaultGraphColours } from './graph.js';
  import { makeDefaultTimePickerOptions} from './graph.js';

  var graphs = { "temp": { name: "temp", element: "#tempGraphElement", cardId: 'temp-graph' },
                "humidity": { name: "humidity", element: "#humidityGraph", cardId: 'humidity-graph' },
                "pressure": { name: "pressure", element: "#pressureGraph", cardId: 'pressure-graph' }
              };

  for (let graphName in graphs) {
    graphs[graphName].options = JSON.parse(JSON.stringify(makeDefaultGraphOptions()));
  }

  graphs['humidity'].options.scales.yAxes[0].ticks.beginAtZero = false;
  graphs['pressure'].options.scales.yAxes[0].ticks.beginAtZero = false;

  $(document).ready(function () {
    window.chartColors = makeDefaultGraphColours();

    for (let graphName in graphs) {
      let graph = graphs[graphName];
      let canvas = $(graph.element).get(0).getContext('2d');

      graph.chart = new Chart(canvas, {
        type: 'line',
        data: {
          datasets: []
        },
        options: graph.options
      });
    }

    $('#querytime').daterangepicker(makeDefaultTimePickerOptions());

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

    $('#querytime').val(startDate.format(dateFormat) + " - " + endDate.format(dateFormat) + " (" + deltaString(startDate, endDate) + ")");
    for (let graphName in graphs) {
      $('#' + graphs[graphName].cardId + ' .overlay').show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);
    $.getJSON("api_db.php?getGraphData3&weather&from=" + startUTC + "&to=" + endUTC,
      function (data) {
        let totalNum = 0;

        let indexToDevice = [];
        let nextIndex = 0;

        const typeToChart = { 0: graphs['temp'].chart, 1: graphs['humidity'].chart, 2: graphs['pressure'].chart };

        for (let graphName in graphs) {
          graphs[graphName].chart.data.labels = [];
        }

        $.each(data,
          function(deviceId, rec0) {

            if (deviceId === 'debug') {
              console.log(rec0);
              return;
            }

            if (!indexToDevice.includes(deviceId)) {
              indexToDevice[nextIndex] = deviceId;
              nextIndex++;
            }
            let deviceIndex = indexToDevice.indexOf(deviceId);

            $.each(rec0,
              function(type, rec1) {

                if (type > 2)
                {
                  return;
                }

                let chart = typeToChart[type];

                chart.data.datasets[deviceIndex] =
                {
                  label: 'Device ' + deviceId,
                  backgroundColor: Object.keys(window.chartColors)[deviceIndex],
                  borderColor: Object.keys(window.chartColors)[deviceIndex],
                  fill: false,
                  data: []
                };

                $.each(rec1,
                  function(timestamp_s, val) {
                    ++totalNum;
                    chart.data.datasets[deviceIndex].data.push(val);
                    chart.data.labels.push(new Date(Number(timestamp_s * 1000)));
                  }
                ); // $.each rec1
              }
            ); // $.each rec0
          }
        ); // $.each data

        console.log("Got " + totalNum + " records");

        let deviceRequests = [];

        // Get device names
        for (let deviceId of indexToDevice) {
          deviceRequests.push($.getJSON("api_db.php?getDeviceDesc&deviceId=" + deviceId));
        }

        $.when.apply($, deviceRequests).done(function() {
          for (let resultIndex in arguments) {
            data = arguments[resultIndex][0];
            let deviceIndex = indexToDevice.indexOf(data['deviceId']);
            for (let graphName in graphs) {
              let graph = graphs[graphName];
              if (graph.chart.data.datasets[deviceIndex] !== undefined) {
                graph.chart.data.datasets[deviceIndex].label = data['friendlyName'];
              }
            }
          }
          for (let graphName in graphs) {
            let graph = graphs[graphName];
            graph.chart.update();
            $('#' + graph.cardId + ' .overlay').hide();
          }
        });
      } // Json handler
    );
  }
</script>

<?php
  require "footer.php"
?>
