<?php
  require "header.php"
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm">
        <h1 class="m-0 text-dark">Energy</h1>
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
        <div class="card card-primary" id="solar-power-graph">
          <div class="card-header">
            <h3 class="card-title">Solar Power</h3>
              <div class="card-tools"> <!-- TODO: Probably unneeded -->
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                </button>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="solarPowerGraphElement" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
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
        <div class="card card-primary" id="solar-energy-graph">
          <div class="card-header">
            <h3 class="card-title">Solar Energy</h3>
              <div class="card-tools"> <!-- TODO: Probably unneeded -->
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                </button>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="solarEnergyGraphElement" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
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
<script type="module">
  "use strict";

  import { makeDefaultGraphOptions } from './graph.js';
  import { deltaString } from './graph.js';
  import { makeDefaultGraphColours } from './graph.js';

  var graphs = { "solarPower": { name: "solarPower", type: 'line', element: "#solarPowerGraphElement", cardId: 'solar-power-graph', label: 'Power output (W)' },
                "solarEnergy": { name: "solarEnergy", type: 'bar', element: "#solarEnergyGraphElement", cardId: 'solar-energy-graph', label: 'Energy produced (Wh)' }
                };
  for (let graphName in graphs) {
    graphs[graphName].options = JSON.parse(JSON.stringify(makeDefaultGraphOptions()));
    //graphs[graphName].options.scales.xAxes[0].ticks = { source: 'labels' };
  }

  $(document).ready(function () {
    for (let graphName in graphs) {
      let graph = graphs[graphName];
      let canvas = $(graph.element).get(0).getContext('2d');

      graph.chart = new Chart(canvas, {
        type: graph.type,
        data: {
          labels: [ ],
          datasets: [ { data: [] }]
        },
        options: graph.options
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
          'Last hour' : [moment().subtract(1, 'hours'), moment()],
          'Last 6 hours' : [ moment().subtract(6, 'hours'), moment()],
          'Today'       : [moment().startOf('day'), moment()],
          'Last 24 hours' : [moment().subtract(24, 'hours'), moment()],
          'Yesterday'   : [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
          'Last 48 hours' : [moment().subtract(48, 'hours'), moment()],
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
    const startDateStr = sessionStorage.getItem('energyStartDate');
    const endDateStr = sessionStorage.getItem('energyEndDate');

    let picker = $('#querytime').data('daterangepicker');
    let startDate = picker.startDate;
    let endDate = picker.endDate;
    if (startDateStr != null && endDateStr != null)
    {

      startDate = moment(startDateStr, picker.locale.format);
      endDate = moment(endDateStr, picker.locale.format);
    }
    fetchAndUpdate(startDate, endDate, picker.locale.format);
  });

  $("#querytime").on("apply.daterangepicker", function (ev, picker) {
    fetchAndUpdate(picker.startDate, picker.endDate, picker.locale.format);
  });

  function fetchAndUpdate(startDate, endDate, dateFormat) {
    sessionStorage.setItem('energyStartDate', startDate.format(dateFormat));
    sessionStorage.setItem('energyEndDate', endDate.format(dateFormat));

    $('#querytime').val(startDate.format(dateFormat) + " - " + endDate.format(dateFormat) + " (" + deltaString(startDate, endDate) + ")");
    for (let graphName in graphs) {
      $('#' + graphs[graphName].cardId + ' .overlay').show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);

    $.getJSON("api_db.php?getGraphData&energy&from=" + startUTC + "&to=" + endUTC,
      function (data) {

        for (let graphName in graphs) {
          let graph = graphs[graphName];
          graph.chart.data.labels = [];
          graph.chart.data.datasets[0] = {
            label:  graph.label,
            backgroundColor: makeDefaultGraphColours().red,
            borderColor: makeDefaultGraphColours().red,
            fill: false,
            data: []
          };
        }

        let previousLifetime_wh = data[0].lifetime_wh;
        $.each(data,
          function(index, entry) {
            graphs['solarPower'].chart.data.datasets[0].data.push(entry.current_w);
            graphs['solarPower'].chart.data.labels.push(new Date(Number(entry.dateTime * 1000)));

            let deltaEnergy = entry.lifetime_wh - previousLifetime_wh;
            previousLifetime_wh = entry.lifetime_wh;
            if (deltaEnergy != 0 || entry.current_w == 0)
            {
              graphs['solarEnergy'].chart.data.datasets[0].data.push(deltaEnergy);
              graphs['solarEnergy'].chart.data.labels.push(new Date(Number(entry.dateTime * 1000)));
            }
            else { console.log('Skipping it...'); }
          }
        );

        console.log(graphs['solarEnergy'].chart.data.datasets[0].data);
        console.log(graphs['solarEnergy'].chart.data.labels);

        for (let graphName in graphs) {
          let graph = graphs[graphName];
          graph.chart.update();
          $('#' + graph.cardId + ' .overlay').hide();
        }
      } // Json handler
    );
  }

</script>

<?php
  require "footer.php"
?>
