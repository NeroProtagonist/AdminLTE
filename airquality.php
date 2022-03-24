<?php
  require "header.php";
  require "chart.php";
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm">
        <h1 class="m-0 text-dark">Air Quality</h1>
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
      newChart("airq", "Air Quality");
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

  var graphs = { "airq": new Graph.Graph('line', 'airq', ['PM1.0', 'PM2.5', 'PM10']) };

  $(document).ready(function () {
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
    const deltaSeconds_str = sessionStorage.getItem('airqPreviousDeltaSeconds');

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

  function resetGraph(graph) {
    graph.chart.data.labels = [];
    let chartColours = Graph.makeDefaultGraphColours();
    for (let i = 0; i < graph.labels.length; ++i) {
      graph.chart.data.datasets[i] = {
        label: graph.labels[i],
        backgroundColor: Object.keys(chartColours)[i],
        borderColor: Object.keys(chartColours)[i],
        fill: false,
        data: []
      };
    }
  }

  function fetchAndUpdate(startDate, endDate, dateFormat) {
    sessionStorage.setItem('airqPreviousDeltaSeconds', endDate.diff(startDate, 'seconds'));

    $('#querytime').val(startDate.format(dateFormat) + " - " + endDate.format(dateFormat) + " (" + Graph.deltaString(startDate, endDate) + ")");
    for (let graphName in graphs) {
      $(`#${graphs[graphName].getCardId()} .overlay`).show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);
    //$.getJSON(`api_db.php?getGraphData3&sensor&from=${startUTC}&to=${endUTC}&types=3,4,5&movingAverage=120`,
    $.getJSON(`api_db.php?getGraphData3&sensor&from=${startUTC}&to=${endUTC}&types=3,4,5`,
      function (data) {
        let totalNum = 0;

        let devices = new Set();

        let [period_s, unit] = Graph.getRawDataPeriod(endUTC - startUTC);

        resetGraph(graphs['airq']);

        $.each(data,
          function(deviceId, rec0) {

            if (deviceId === 'debug') {
              console.log(rec0);
              return;
            }

            devices.add(deviceId);

            let chart = graphs['airq'].chart
            const typeToDataset = { 3 : chart.data.datasets[0],
                                    4 : chart.data.datasets[1],
                                    5 : chart.data.datasets[2] };

            $.each(rec0,
              function(type, rec1) {
                let dataset = typeToDataset[type];
                $.each(rec1,
                  function(timestamp_s, val) {
                    ++totalNum;
                    dataset.data.push({ x: new Date(Number(timestamp_s * 1000)), y: val});
                  }
                ); // $.each rec1
              }
            ); // $.each rec0
          }
        ); // $.each data

        console.log("Got " + totalNum + " records");

        let deviceRequests = [];

        // Get device names
        for (const deviceId of devices) {
          deviceRequests.push($.getJSON("api_db.php?getDeviceDesc&deviceId=" + deviceId));
        }

        $.when.apply($, deviceRequests).done(function() {
          let responses = deviceRequests.length === 1 ? [arguments] : arguments;
          for (let graphName in graphs) {
            let graph = graphs[graphName];
            graph.chart.options.scales.xAxes[0].time.unit = unit;
            graph.chart.options.scales.xAxes[0].time.stepSize = period_s / Graph.getSeconds(unit);
            graph.chart.update();
            $(`#${graph.getCardId()} .overlay`).hide();
          }
        });
      } // Json handler
    );
  }
</script>

<?php
  require "footer.php"
?>
