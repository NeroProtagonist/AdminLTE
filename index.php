<?php
  require "header.php"
?>

<script src="plugins/moment/moment.min.js"></script>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm">
        <h1 class="m-0 text-dark">Overview</h1>
      </div>
      <div class="col-sm">
        <div class="float-sm-right">
          <input type="checkbox" name="refreshStats" id="refreshStatsToggle">
          <label for="refreshStatsToggle">Refresh</label>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="content">
  <div class="card" class="container-fluid">
    <div class="card-header">
      <h3 class="card-title">
        Weather
      </h3>
    </div>
    <div class="card-body">
      <div id="weatherInsert">
        <!-- Insertion place for weather sensors -->

      </div> <!-- .container-fluid -->
    </div>
  </div>

  <div class="card" class="container-fluid">
    <div class="card-header">
      <h3 class="card-title">
        Energy
      </h3>
    </div>
    <div class="card-body">
      <div id="meterInsert">
        <!-- Insertion place for energy meter data -->

      </div> <!-- .container-fluid -->
      <div id="solarInsert">
        <!-- Insertion place for solar data -->

      </div> <!-- .container-fluid -->
    </div>
  </div>
</div>

<!-- Bootstrap Switch -->
<script src="../../plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>

<!-- page script -->
<script>
  "use strict";

  let devs = [];

  function getWeatherDisplaySet(deviceId, value) {
    let val = Number(value['value']);
    switch (Number(value['type'])) {
      case 0: return { id: "device" + deviceId + "_type" + value['type'], value: val.toFixed(1) + '&#x2103;', type: 'Temperature', bg: 'primary' };
      case 1: return { id: "device" + deviceId + "_type" + value['type'], value: val.toFixed(0) + '%', type: 'Relative Humidity', bg: 'secondary' };
      case 2: return { id: "device" + deviceId + "_type" + value['type'], value: val.toFixed(1), type: 'Pressure', bg: 'success' };
    }
  }

  function getMeterDisplaySet(value) {
    return { id: `meter_${value['stat']}`, text : `${value['value']} ${value['unit']}` };
  }

  let energyStatsLayout = [ [ 9, 10 ] ];
  let energyStatsRowDesc = [ 'Power' ];

  function initStats() {
    $.getJSON("api_db.php?getDeviceIds",
      function(data) {
        // Clear everything under insertion point
        $('#weatherInsert').empty();

        // Insert divs for each device so that devices are ordered on page by device Id
        for (let deviceId of data) {
          $('#weatherInsert').append(`<div id=dev${deviceId}></div>`);
        }

        devs = data;

        for (let deviceId of devs) {
          $.getJSON("api_db.php?getDeviceDesc&deviceId=" + deviceId,
            function(desc) {
              $.getJSON("api_db.php?getLastValues&weather&deviceId=" + deviceId,
                function(values) {
                  // Device name
                  let sampleTime = moment(new Date(Number(values[0].ts * 1000)));
                  let txt = `
                    <div class="row">
                      <div class="col-sm">
                        <h5 class="mb-2">${desc['friendlyName']}</h5>
                      </div>
                      <div class="col-sm">
                        <div class="float-sm-right">
                          <p class="mb-2">${sampleTime.format('ddd DD/MM/YY HH:mm:ss')}</p>
                        </div>
                      </div>
                    </div>
                    <div class="row">`;
                  for (let value of values) {
                    let displaySet = getWeatherDisplaySet(deviceId, value);
                    // One box per stat on the same row
                    txt += `
                      <div class="col-md-3">
                        <div class="small-box bg-${displaySet.bg}">
                          <div class="inner">
                            <h3 id="${displaySet.id}">${displaySet.value}</h3>
                            <p>${displaySet.type}</p>
                          </div>
                          <div class="icon">
                            <i class="fas fa-thermometer-half"></i>
                          </div>
                          <a href="weather.php" class="small-box-footer">
                            Data <i class="fas fa-arrow-circle-right"></i>
                          </a>
                        </div>
                      </div>`;
                  }
                  txt += `</div>`;
                  $(`#dev${deviceId}`).append(txt);
                }); // getLastValues&weather
            }); // getDeviceDesc
        }
      }); // getDeviceIds

    $('#meterInsert').empty()
    $.getJSON("api_db.php?getLastValues&meter&stats=9,10",
      function(stats) {
        let row = 0;
        for (let statRow in energyStatsLayout) {
          let sampleTime = moment(new Date(Number(stats[energyStatsLayout[statRow][0]].ts * 1000)));
          let txt = `
            <div class="row">
              <div class="col-sm">
                <h5 class="mb-2">${energyStatsRowDesc[row]}</h5>
              </div>
              <div class="col-sm">
                <div class="float-sm-right">
                  <p class="mb-2">${sampleTime.format('ddd DD/MM/YY HH:mm:ss')}</p>
                </div>
              </div>
            </div>
            <div class="row">`;
          for (let stat of energyStatsLayout[statRow]) {
            let displaySet = getMeterDisplaySet(stats[stat]);
            txt += `
              <div class="col-md-6">
                <div class="small-box bg-primary">
                  <div class="inner">
                    <h3 id="${displaySet.id}">${displaySet.text}</h3>
                    <p>${stats[stat]['description']}</p>
                  </div>
                  <div class="icon">
                    <i class="fas fa-bolt"></i>
                  </div>
                  <a href="energy.php" class="small-box-footer">
                    Data <i class="fas fa-arrow-circle-right"></i>
                  </a>
                </div>
              </div>
              `;
          }
          txt += `</div>`;
          $('#meterInsert').append(txt)
          ++row;
        }
      }); // getLastValues&meter
  }

  let statsRefresh = [
    { updateFunc: function() {
                    for (let deviceId of devs) {
                      $.getJSON("api_db.php?getLastValues&weather&deviceId=" + deviceId,
                        function(values) {
                          for (let value of values) {
                            let displaySet = getWeatherDisplaySet(deviceId, value);
                            $(`#${displaySet.id}`).html(displaySet.value);
                          }
                        });
                    }
                  },
      period: 5000
    },
    {
      updateFunc: function() {
                    $.getJSON("api_db.php?getLastValues&meter",
                      function(stats) {
                        for (let stat in stats) {
                          let displaySet = getMeterDisplaySet(stats[stat]);
                          $(`#${displaySet.id}`).html(displaySet.text);
                        }
                      }); // getLastValues&meter
                  },
      period: 1000
    }
  ];

  $(document).ready(function() {

    $("[name='refreshStats'").bootstrapSwitch();
    $("[name='refreshStats'").on('switchChange.bootstrapSwitch', function(event, state) {
      if (state) {
        for (let s of statsRefresh) {
          s.timer = setInterval(s.updateFunc, s.period);
        }
      } else {
        for (let s of statsRefresh) {
          clearInterval(s.timer);
        }
      }
    });

    initStats();
  });
</script>

<?php
  require "footer.php"
?>
