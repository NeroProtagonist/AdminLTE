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
            <select id="meterPeriod", class="form-control">
              <option value="meter_1m">1 minute</option>
              <option value="meter_5m">5 minutes</option>
              <option value="meter_15m" selected>15 minutes</option>
              <option value='meter_1h''>Hourly</option>
              <option value='meter_24h'>Daily</option>
            </select>
          </div> <!-- .card-body -->
        </div> <!-- .card -->
      </div>
    </div> <!-- .row -->

    <?php
      newChart("meter-elec-received", "Elec Received");
      newChart("meter-power-received", "Power Received");
      newChart("meter-phase-power", "Phase Power");
      newChart("meter-phase-voltage", "Phase Voltage");
      newChart("meter-phase-current", "Phase Current");
      newChart("meter-gas-received", "Gas Received");
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
<script src="plugins/chart.js/Chart.js"></script>
<script src="plugins/daterangepicker/daterangepicker.js"></script>
<!-- page script -->
<script type="module">
  "use strict";

  import { deltaString } from './graph.js';
  import { makeDefaultGraphColours } from './graph.js';
  import { makeDefaultTimePickerOptions} from './graph.js';
  import { Graph } from './graph.js';
  import { getSeconds } from './graph.js';

  let solarGraphs = new Map([ ["solarPower", new Graph('line', "solar-power", [ 'Power output (W)' ])],
                              ["solarEnergy", new Graph('bar', "solar-energy", [ 'Energy produced (Wh)' ])]
                            ]);
  let meterGraphs = new Map([ ['elecReceived', new Graph('bar', 'meter-elec-received', [ 'Energy from supplier (kWh)', 'Energy to supplier (kWh)' ])],
                              ['powerReceived', new Graph('line', 'meter-power-received', [ 'Power from supplier (kW)', 'Power to supplier (kW)' ])],
                              ['phasePower', new Graph('line', 'meter-phase-power', [ 'L1', 'L2', 'L3' ] )],
                              ['phaseVoltage', new Graph('line', 'meter-phase-voltage', [ 'L1', 'L2', 'L3'] )],
                              ['phaseCurrent', new Graph('line', 'meter-phase-current', [ 'L1', 'L2', 'L3'] )],
                              ['gasReceived', new Graph('bar', 'meter-gas-received', [ 'Gas from supplier (m^3)' ])]
                            ]);

  let graphs = new Map();

  for (let [name, g] of solarGraphs.entries()) {
    graphs.set(name, g);
  }
  for (let [name, g] of meterGraphs.entries()) {
    graphs.set(name, g);
  }

  let statToPrice = { 4: 0,
                        5: 0,
                        33: 0 };

  function getPeriod(str) {
    let [period_s, unit, startDate] = function() {
      switch (str) {
        case 'meter_1m': return [ 60, 'minute', moment().subtract(30, 'minutes').startOf('minute') ]
        case 'meter_5m': return [ 5 * 60, 'minute', moment().subtract(2, 'hours').startOf('hour') ];
        case 'meter_15m': return [ 15 * 60, 'minute', moment().subtract(6, 'hours').startOf('hour') ];
        case 'meter_1h': return [ 60 * 60, 'hour', moment().startOf('day') ];
        case 'meter_24h': return [ 24 * 60 * 60, 'day', moment().startOf('month') ];
      }
      return  [ 0, moment() ];
    }();
    return [ period_s, unit, startDate, moment() ];
  }

  $(document).ready(function () {
    for (let [, graph] of graphs) {
      let canvas = $(`#${graph.getElement()}`).get(0).getContext('2d');

      graph.chart = new Chart(canvas, {
        type: graph.type,
        options: graph.options
      });
    }

    $('#querytime').daterangepicker(makeDefaultTimePickerOptions());

    // Initial meter fetch
    initPrices()
    console.log(statToPrice);
    fetchAndUpdateMeter()

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

  $('#meterPeriod').change(function() {
    fetchAndUpdateMeter();
  });

  function resetGraph(graph) {
    graph.chart.data.labels = [];
    let chartColours = makeDefaultGraphColours();
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

  function initPrices() {
    $.ajax( {
      dataType: "json",
      url: "api_db.php?getPrices&meter",
      success: function(data) {
          for (let g of data) {
            statToPrice[Number(g.stat)] = Number(g.value);
          }
        },
      async: false
      });
  }

  function fetchAndUpdateMeter() {
    let v = $('#meterPeriod option:selected').val();
    let [period_s, unit, startDate, endDate] = getPeriod(v);

    for (let [, graph] in meterGraphs) {
      $(`#${graph.getCardId()} .overlay`).show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);

    $.getJSON(`api_db.php?getGraphData&meter&from=${startUTC}&to=${endUTC}&period_s=${period_s}`,
      function(data) {
        for (let [, graph] of meterGraphs) {
          resetGraph(graph);
        }

        // Find first elec value
        let prevValue = [];
        let prevTS = [];

        const statToChart = { 4: { chart: meterGraphs.get('elecReceived').chart, dataset: 0 },
                              5: { chart: meterGraphs.get('elecReceived').chart, dataset: 0 },
                              6: { chart: meterGraphs.get('elecReceived').chart, dataset: 1 },
                              7: { chart: meterGraphs.get('elecReceived').chart, dataset: 1 },
                              9: { chart: meterGraphs.get('powerReceived').chart, dataset: 0 },
                              10: { chart: meterGraphs.get('powerReceived').chart, dataset: 1 },
                              20: { chart: meterGraphs.get('phaseVoltage').chart, dataset: 0 },
                              21: { chart: meterGraphs.get('phaseVoltage').chart, dataset: 1 },
                              22: { chart: meterGraphs.get('phaseVoltage').chart, dataset: 2 },
                              23: { chart: meterGraphs.get('phaseCurrent').chart, dataset: 0 },
                              24: { chart: meterGraphs.get('phaseCurrent').chart, dataset: 1 },
                              25: { chart: meterGraphs.get('phaseCurrent').chart, dataset: 2 },
                              26: { chart: meterGraphs.get('phasePower').chart, dataset: 0 },
                              27: { chart: meterGraphs.get('phasePower').chart, dataset: 1 },
                              28: { chart: meterGraphs.get('phasePower').chart, dataset: 2 },
                              33: { chart: meterGraphs.get('gasReceived').chart, dataset: 0 },
                            };

        $.each(data,
          function(index, entry) {

            if (index === 'debug') {
              console.log(entry);
              return;
            }

            let stat = Number(entry.stat);
            if (prevValue[stat] == null) {
              prevValue[stat] = entry.value;
            }
            let deltaV = entry.value - prevValue[stat];
            prevValue[stat] = entry.value;
            if (prevTS[stat] == null) {
              prevTS[stat] = Number(entry.ts);
            }
            let deltaT = Number(entry.ts) - prevTS[stat];
            prevTS[stat] = Number(entry.ts);

            if (statToChart[stat] == null) {
              return;
            }
            let chart = statToChart[stat].chart;
            let dataset = chart.data.datasets[statToChart[stat].dataset];

            switch (stat) {
              case 4:
              case 5:
              case 6:
              case 7:
              case 33:
              {
                if (deltaV != 0 && deltaT > period_s * 0.9) {
                  let tVal = Number(entry.ts) - deltaT / 2;
                  dataset.data.push( { t: new Date(tVal * 1000), y: deltaV } );
                }

                break;
              }
              case 9:
              case 10:
              case 20:
              case 21:
              case 22:
              case 23:
              case 24:
              case 25:
              case 26:
              case 27:
              case 28:
              {
                if (deltaT > period_s * 0.95) {
                  // TODO: Could choose max of this and current
                  dataset.data.push({ t: new Date(Number(entry.ts * 1000)), y: Number(entry.value) } );
                }
                break;
              }
            }
          });

        for (let [, graph] of meterGraphs) {
          graph.chart.options.scales.xAxes[0].time.unit = unit;
          graph.chart.options.scales.xAxes[0].time.stepSize = period_s / getSeconds(unit);
          graph.chart.options.scales.xAxes[0].ticks.min = startDate;
          graph.chart.options.scales.xAxes[0].ticks.max = endDate;
        }

        for (let [, graph] of meterGraphs) {
          graph.chart.update();
          $(`#${graph.getCardId()} .overlay`).hide();
        }
      });
  }

  function fetchAndUpdate(startDate, endDate, dateFormat) {
    sessionStorage.setItem('solarPreviousDeltaSeconds', endDate.diff(startDate, 'seconds'));

    $('#querytime').val(startDate.format(dateFormat) + " - " + endDate.format(dateFormat) + " (" + deltaString(startDate, endDate) + ")");
    for (let [, graph] in solarGraphs) {
      $(`#${graph.getCardId()} .overlay`).show();
    }

    let startUTC = Math.trunc(startDate.valueOf() / 1000);
    let endUTC = Math.trunc(endDate.valueOf() / 1000);

    $.getJSON("api_db.php?getGraphData&solar&from=" + startUTC + "&to=" + endUTC,
      function (data) {

        for (let [, graph] of solarGraphs) {
          resetGraph(graph);
        }

        solarGraphs.get('solarEnergy').chart.data.datasets[0].barThickness = 'flex';

        if (data.length > 0) {
          let previousLifetime_wh = data[0].lifetime_wh;
          $.each(data,
            function(index, entry) {
              solarGraphs.get('solarPower').chart.data.datasets[0].data.push(entry.current_w);
              solarGraphs.get('solarPower').chart.data.labels.push(new Date(Number(entry.ts * 1000)));

              let deltaEnergy = entry.lifetime_wh - previousLifetime_wh;
              previousLifetime_wh = entry.lifetime_wh;
              if (deltaEnergy != 0 || entry.current_w == 0)
              {
                solarGraphs.get('solarEnergy').chart.data.datasets[0].data.push(deltaEnergy);
                solarGraphs.get('solarEnergy').chart.data.labels.push(new Date(Number(entry.ts * 1000)));
              }
              else { console.log('Skipping it...'); }
            }
          );
        }

        for (let [, graph] of solarGraphs) {
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
