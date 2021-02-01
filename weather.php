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

  </div><!-- /.container-fluid -->
</div>
<!-- /.content -->
</div>

<!-- ChartJS -->
<script src="plugins/moment/moment.min.js"></script>
<!-- <script src="plugins/chart.js/Chart.min.js"></script> -->
<script src="plugins/chart.js/Chart.js"></script>
<script src="plugins/daterangepicker/daterangepicker.js"></script>
<!-- page script -->
<script>
  "use strict";

  var graphs = { "temp": { name: "temp", element: "#tempGraphElement", cardId: 'temp-graph' },
                 "humidity": { name: "humidity", element: "#humidityGraph", cardId: 'humidity-graph' }
                };

  $(function () {

    let defaultGraphOptions = {
      maintainAspectRatio: false,
      responsive: true,
      datasetFill: false,
      scales: {
        xAxes: [
          {
            type: 'time',
            time: {
              minUnit: 'minute',
              displayFormats: {
                second: 'HH:mm:ss',
                minute: 'HH:mm',
                hour: 'HH'
              },
              ticks: {
                //sampleSize: 20
              }
            }
          }
        ],
        yAxes: [{
          ticks: {
            beginAtZero: true
          }
        }]
      },
      animation: false
    }

    window.chartColors = {
      red: 'rgb(255, 99, 132)',
      orange: 'rgb(255, 159, 64)',
      yellow: 'rgb(255, 205, 86)',
      green: 'rgb(75, 192, 192)',
      blue: 'rgb(54, 162, 235)',
      purple: 'rgb(153, 102, 255)',
      grey: 'rgb(201, 203, 207)'
    };

    for (let graphName in graphs) {
      let graph = graphs[graphName];
      let canvas = $(graph.element).get(0).getContext('2d');

      graph.chart = new Chart(canvas, {
        type: 'line',
        data: {
          datasets: []
        },
        options: defaultGraphOptions
      });
    }

    $('#querytime').daterangepicker(
      {
        timePicker: true,
        timePickerIncrement: 15,
        timePicker24Hour: true,
        locale: {
          format: "DD/MM/YYYY HH:mm"
        },
        ranges : {
          'Last 5 minutes'  : [moment().subtract(5, 'minutes'), moment()],
          'Last 30 minutes' : [moment().subtract(30, 'minutes'), moment()],
          'Today'       : [moment().startOf('day'), moment()],
          'Yesterday'   : [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
          'Last 7 Days' : [moment().subtract(6, 'days'), moment()],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Month'  : [moment().startOf('month'), moment().endOf('month')],
          'This Year'   : [moment().startOf("year"), moment()],
          'All Time'    : [moment(0), moment()]
        },
        startDate: moment().subtract(1, 'hours'), // Default
        endDate: moment(),
        opens: 'center',
        autoUpdateInput: false
      }
    )

    // Initial fetch
    let picker = $('#querytime').data('daterangepicker');
    fetchAndUpdate(picker.startDate, picker.endDate, picker.locale.format);
  });

  $("#querytime").on("apply.daterangepicker", function (ev, picker) {
    fetchAndUpdate(picker.startDate, picker.endDate, picker.locale.format);
  });

  function fetchAndUpdate(startDate, endDate, dateFormat) {
    $('#querytime').val(startDate.format(dateFormat) + " - " + endDate.format(dateFormat) + " (" + startDate.diff(endDate) + ")");
    for (let graphName in graphs) {
      $('#' + graphs[graphName].cardId + ' .overlay').show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);
    $.getJSON("api_db.php?getGraphData&weather&from=" + startUTC + "&to=" + endUTC,
      function (data) {
        let totalNum = 0;

        let indexToDevice = [];
        let nextIndex = 0;

        const typeToChart = { 0: graphs['temp'].chart, 1: graphs['humidity'].chart };

        for (let graphName in graphs) {
          graphs[graphName].chart.data.labels = [];
        }

        $.each(data,
          function(deviceId, rec0) {
            if (!indexToDevice.includes(deviceId)) {
              indexToDevice[nextIndex] = deviceId;
              nextIndex++;
            }
            let deviceIndex = indexToDevice.indexOf(deviceId);

            $.each(rec0,
              function(type, rec1) {

                if (type > 1)
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
