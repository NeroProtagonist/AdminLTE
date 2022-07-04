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
  import * as AQI from './aqi.js';

  var graphs = { "airq": new Graph.Graph('line', 'airq', ['PM1.0', 'PM2.5', 'PM10']) };

  $(document).ready(function () {
    for (let graphName in graphs) {
      let graph = graphs[graphName];
      graph.options.scales.yAxes[0].id = 'pmAxis';
      graph.options.scales.yAxes[1] = { id: 'aqiAxis',
                                        position: 'right',
                                        ticks: { beginAtZero: true } };

      //graph.options.legend = { display: false }; TODO: Remove AQI from legend

      let canvas = $(`#${graph.getElement()}`).get(0).getContext('2d');

      graph.chart = new Chart(canvas, {
        type: graph.type,
        options: graph.options
      });
    }

    $('#querytime').daterangepicker(Graph.makeDefaultTimePickerOptions());

    // Initial fetch
    const deltaSeconds_str = sessionStorage.getItem('airqPreviousDeltaSeconds');
    const endDate_str = sessionStorage.getItem('airqPreviousEndDate');

    let picker = $('#querytime').data('daterangepicker');
    let startDate = picker.startDate;
    let endDate = picker.endDate;
    let clearStorage = false;
    if (deltaSeconds_str != null && endDate_str != null)
    {
      endDate = moment(endDate_str, "X");
      startDate = endDate.clone();
      startDate.subtract(deltaSeconds_str, 'seconds');
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
        yAxisID: 'pmAxis',
        backgroundColor: Object.keys(chartColours)[i],
        borderColor: Object.keys(chartColours)[i],
        fill: false,
        data: []
      };
    }
    const aqiCatColours = [ '#00B050',  // Green
                            '#FFFF00',  // Yellow
                            '#FF6600',  // Orange
                            '#FF0000',  // Red
                            '#7030A0', // Purple
                            '#990033' // Maroon
                          ];
    const aqiCatColoursLight = [ '#005828',  // Green
                                 '#7F7F00',  // Yellow
                                 '#7F3300',  // Orange
                                 '#7F0000',  // Red
                                 '#381850', // Purple
                                 '#4C0019' // Maroon
    ];

    function transparentize(color, opacity) {
      var alpha = opacity === undefined ? 0.5 : 1 - opacity;
      return Color(color).alpha(alpha).rgbString();
    }

    function colorize(opaque, ctx) {
      const aqi = ctx.dataset.data[ctx.dataIndex].y;
      const cat = ctx.dataset.data[ctx.dataIndex].cat;
      return opaque ? aqiCatColours[cat] : transparentize(aqiCatColours[cat], 0.3);
    }

    // AQI
    graph.chart.data.datasets[graph.labels.length] = {
        label: 'AQI',
        yAxisID: 'aqiAxis',
        backgroundColor: colorize.bind(null, false),
        borderColor: colorize.bind(null, false),
        type: 'bar',
        data: [],
      };
  }

  function fetchAndUpdate(startDate, endDate, dateFormat) {
    sessionStorage.setItem('airqPreviousDeltaSeconds', endDate.diff(startDate, 'seconds'));
    sessionStorage.setItem('airqPreviousEndDate', endDate.unix());

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

            let tsToAQI = new Map();

            $.each(rec0,
              function(type, rec1) {
                let dataset = typeToDataset[type];
                let base;
                if (type == 4) {
                  base = AQI.getPM25Base();
                } else if (type == 5) {
                  base = AQI.getPM10Base();
                }
                $.each(rec1,
                  function(timestamp_s, val) {
                    ++totalNum;
                    let ts = Number(timestamp_s);
                    let date = new Date(ts * 1000);
                    dataset.data.push({ x: date, y: val});
                    if (type == 4 || type == 5) {
                      let aqi = AQI.getAQI(base, AQI.getAQIBase(), val);
                      let curAQI = tsToAQI.get(ts);
                      if (curAQI !== undefined) {
                        curAQI.cat = Math.max(curAQI.cat, aqi.cat);
                        curAQI.aqi = Math.max(curAQI.aqi, aqi.aqi);
                      } else {
                        tsToAQI.set(ts, aqi);
                      }
                    }
                  }
                ); // $.each rec1
              }
            ); // $.each rec0

            for (let [ts, aqi] of tsToAQI) {
              chart.data.datasets[3].data.push({ x: new Date(ts * 1000), y: aqi.aqi, cat: aqi.cat });
            }
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
