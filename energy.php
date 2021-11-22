<?php
  require "header.php";
  require "chart.php";
?>

<!-- Meter -->
<div class="content-header">
  <div class="container-fluid">
    <h1>Meter</h1>
  </div>
</div>

<section class="content">
  <div class="container-fluid">

    <div class="row">
      <div class="col-md-12">
        <!-- Time selection card -->
        <div class="card card-primary">
          <div class="card-header">
            <h3 class="card-title">
              Select period
            </h3>
          </div> <!-- .card-header -->
          <div class="card-body">
            <select class="form-control">
              <option>15 minutes</option>
              <option>Hourly</option>
              <option>Daily</option>
            </select>
          </div> <!-- .card-body -->
        </div> <!-- .card -->
      </div>
    </div> <!-- .row -->

    <?php
      newChart("meter-elec-received", "Elec Received");
    ?>
  </div> <!-- .container-fluid -->
</section>

<!-- Solar -->
<div class="content-header">
  <div class="container-fluid">
    <h1>Solar</h1>
  </div>
</div>

<section class="content">
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
          </div> <!-- .card-body -->
        </div> <!-- .card -->
      </div>
    </div><!-- .row -->

    <?php
      newChart("solar-power", "Solar Power");
      newCHart("solar-energy", "Solar Energy")
    ?>

  </div><!-- /.container-fluid -->
</section><!-- /.content -->

<!-- ChartJS -->
<script src="plugins/moment/moment.min.js"></script>
<script src="plugins/chart.js/Chart.min.js"></script>
<!--<script src="plugins/chart.js/Chart.js"></script>-->
<script src="plugins/daterangepicker/daterangepicker.js"></script>
<!-- page script -->
<script type="module">
  "use strict";

  import { deltaString } from './graph.js';
  import { makeDefaultGraphColours } from './graph.js';
  import { makeDefaultTimePickerOptions} from './graph.js';
  import { Graph } from './graph.js';

  let solarGraphs = new Map([ ["solarPower", new Graph('line', "solar-power", 'Power output (W)')],
                              ["solarEnergy", new Graph('bar', "solar-energy", 'Energy produced (Wh)')]
                            ]);
  let meterGraphs = new Map([['elecReceived', new Graph('bar', 'meter-elec-received', 'Energy from supplier (kWh)')]
                            ]);

  let graphs = new Map();

  for (let [name, g] of solarGraphs.entries()) {
    graphs.set(name, g);
  }
  for (let [name, g] of meterGraphs.entries()) {
    graphs.set(name, g);
  }

  $(document).ready(function () {
    for (let [graphName, graph] of graphs) {
      let canvas = $(`#${graph.getElement()}`).get(0).getContext('2d');

      graph.chart = new Chart(canvas, {
        type: graph.type,
        data: {
          labels: [ ],
          datasets: [ { data: [] }]
        },
        options: graph.options
      });
    }

    $('#querytime').daterangepicker(makeDefaultTimePickerOptions());

    // Initial meter fetch

    // Initial solar fetch
    const solarDeltaSeconds_str = sessionStorage.getItem('solarPreviousDeltaSeconds');

    let picker = $('#querytime').data('daterangepicker');
    let startDate = picker.startDate;
    let endDate = picker.endDate;
    if (solarDeltaSeconds_str != null)
    {
      startDate = moment().subtract(solarDeltaSeconds_str, 'seconds');
      endDate = moment();
    }
    fetchAndUpdate(startDate, endDate, picker.locale.format);
  });

  $("#querytime").on("apply.daterangepicker", function (ev, picker) {
    fetchAndUpdate(picker.startDate, picker.endDate, picker.locale.format);
  });

  function fetchAndUpdate(startDate, endDate, dateFormat) {
    sessionStorage.setItem('solarPreviousDeltaSeconds', endDate.diff(startDate, 'seconds'));

    $('#querytime').val(startDate.format(dateFormat) + " - " + endDate.format(dateFormat) + " (" + deltaString(startDate, endDate) + ")");
    for (let [graphName, graph] in solarGraphs) {
      $(`#${graph.getCardId()} .overlay`).show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);

    $.getJSON("api_db.php?getGraphData&solar&from=" + startUTC + "&to=" + endUTC,
      function (data) {

        for (let [graphName, graph] of solarGraphs) {
          graph.chart.data.labels = [];
          graph.chart.data.datasets[0] = {
            label:  graph.label,
            backgroundColor: makeDefaultGraphColours().red,
            borderColor: makeDefaultGraphColours().red,
            fill: false,
            data: []
          };
        }

        solarGraphs.get('solarEnergy').chart.data.datasets[0].barThickness = 'flex';

        if (data.length > 0) {
          let previousLifetime_wh = data[0].lifetime_wh;
          $.each(data,
            function(index, entry) {
              solarGraphs.get('solarPower').chart.data.datasets[0].data.push(entry.current_w);
              solarGraphs.get('solarPower').chart.data.labels.push(new Date(Number(entry.dateTime * 1000)));

              let deltaEnergy = entry.lifetime_wh - previousLifetime_wh;
              previousLifetime_wh = entry.lifetime_wh;
              if (deltaEnergy != 0 || entry.current_w == 0)
              {
                solarGraphs.get('solarEnergy').chart.data.datasets[0].data.push(deltaEnergy);
                solarGraphs.get('solarEnergy').chart.data.labels.push(new Date(Number(entry.dateTime * 1000)));
              }
              else { console.log('Skipping it...'); }
            }
          );
        }

        for (let [graphName, graph] of solarGraphs) {
          graph.chart.update();
          $(`#${graph.getCardId()} .overlay`).hide();
        }
      } // Json handler
    );
  }

</script>

<?php
  require "footer.php"
?>
