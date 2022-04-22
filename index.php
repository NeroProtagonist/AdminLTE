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

      </div>
      <div id="solarInsert">
        <!-- Insertion place for solar data -->

      </div>
    </div>
  </div> <!-- .container-fluid -->

  <div class="card" class="container-fluid">
    <div class="card-header">
      <h3 class="card-title">
        Air Quality
      </h3>
    </div>
    <div class="card-body">
      <div id="airqInsert">
        <!-- Insertion place for air quality data -->
      </div>
    </div>
  </div>
</div>

<!-- Bootstrap Switch -->
<script src="../../plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>

<!-- page script -->
<script type='module'>
  "use strict";

  import * as AQI from './aqi.js';

  function getSensorDisplaySet(deviceId, type, val) {
    let makeId = (deviceId, type) => "device" + deviceId + "_type" + type;
    let makeBgId = (deviceId, type) => makeId(deviceId, type) + "_bg";
    switch (type) {
      case 0: return { id: makeId(deviceId, type), bgId: makeBgId(deviceId, type), value: val.toFixed(1) + '&#x2103;', type: 'Temperature', bg: 'primary', icon: 'fa-thermometer-half' };
      case 1: return { id: makeId(deviceId, type), bgId: makeBgId(deviceId, type), value: val.toFixed(0) + '%', type: 'Relative Humidity', bg: 'secondary', icon: 'fa-thermometer-half' };
      case 2: return { id: makeId(deviceId, type), bgId: makeBgId(deviceId, type), value: val.toFixed(1), type: 'Pressure', bg: 'success', icon: 'fa-thermometer-half' };
      case 3: return { id: makeId(deviceId, type), bgId: makeBgId(deviceId, type), value: val.toFixed(1), type: 'PM 1.0', bg: 'secondary', icon: 'fa-heart' };
      case 4: return { id: makeId(deviceId, type), bgId: makeBgId(deviceId, type), value: val.toFixed(1), type: 'PM 2.5', bg: 'secondary', icon: 'fa-heart' };
      case 5: return { id: makeId(deviceId, type), bgId: makeBgId(deviceId, type), value: val.toFixed(1), type: 'PM 10.0', bg: 'secondary', icon: 'fa-heart' };
    }
  }

  function getMeterDisplaySet(value) {
    return { id: `meter_${value['stat']}`, text : `${value['value']} ${value['unit']}` };
  }

  let allDevs = [];
  const energyStatsLayout = [ [ 9, 10 ] ];
  const statDescOverride = new Map( [ [9, "Power Received"],
                                  [10, "Power Sent" ] ]);
  const energyStatsRowDesc = [ 'Power' ];

  let aqiDevices = [];
  function getAQIElements(device) {
    return { value: `device${device}_aqi_value`, bg: `device${device}_aqi_bg` };
  }

  function getSampleTimeElement(dev) {
    return `device${dev}_sampleTime`;
  }

  function updateSensorValue(dev) {
    const timeLimitUTC = Math.trunc(moment().subtract(24, "hours").valueOf() / 1000);
    const maQuery = dev.movingAverage !== null ? `&movingAverage=${dev.movingAverage}` : ``;
    $.getJSON(`api_db.php?getLastValues&sensor&deviceId=${dev.id}&ignoreOlderThan=${timeLimitUTC}${maQuery}`,
      function(values) {
        if (values.length === 0) {
          return;
        }
        const sampleTime = moment(new Date(Number(values[0].ts * 1000)));
        const sampleColour = moment().diff(sampleTime, 'hours') > 1 ? 'bg-warning' : '';
        const sampleId = getSampleTimeElement(dev.id);
        $(`#${sampleId}`).removeClass();
        $(`#${sampleId}`).addClass(`mb-2 ${sampleColour}`);
        $(`#${sampleId}`).html(sampleTime.format('ddd DD/MM/YY HH:mm:ss'));


        let pm25AQI = null, pm10AQI = null;
        for (let value of values) {
          const type = Number(value['type']);
          const val = Number(value['value']);
          let displaySet = getSensorDisplaySet(dev.id, type, val)
          $(`#${displaySet.id}`).html(displaySet.value);

          if (type == 4 || type == 5) {
            let scheme;
            if (type == 4) {
              pm25AQI = AQI.getAQI(AQI.getPM25Base(), AQI.getAQIBase(), val);
              scheme = AQI.getScheme(pm25AQI.cat);
            } else if (type == 5) {
              pm10AQI = AQI.getAQI(AQI.getPM10Base(), AQI.getAQIBase(), val);
              scheme = AQI.getScheme(pm10AQI.cat);
            }

            $(`#${displaySet.id}`).after(`<h3>${scheme.text}</h3>`);
            $(`#${displaySet.bgId}`).removeClass();
            $(`#${displaySet.bgId}`).addClass(`small-box ${scheme.color}`);
          }
        }

        // Update AQI pseudo stat
        if (pm10AQI !== null && pm25AQI != null) {
          const elements = getAQIElements(dev.id);
          const scheme = AQI.getScheme(Math.max(pm10AQI.cat, pm25AQI.cat));
          let text = scheme.text;
          if (pm25AQI.aqi > pm10AQI.aqi) {
            text += " (PM2.5)";
          } else if (pm25AQI.aqi < pm10AQI.aqi) {
            text += " (PM10)";
          } else {
            text += " (PM2.5 = PM10)";
          }
          $(`#${elements.value}`).html(text);
          $(`#${elements.bg}`).removeClass();
          $(`#${elements.bg}`).addClass(`small-box ${scheme.color}`)
        }
      });
  }

  function updateSensorValues() {
    for (let dev of allDevs) {
      updateSensorValue(dev);
    }
  }

  function createSensorLayout(devs, movingAverage, link) {

    for (let dev of devs) {
      const descReq = $.getJSON(`api_db.php?getDeviceDesc&deviceId=${dev.id}`);
      const typeReq = $.getJSON(`api_db.php?getSensorDeviceTypes&deviceId=${dev.id}`);
      $.when(descReq, typeReq).done(
        function(descData, typesData) {
          const desc = descData[0];
          const types = typesData[0];
          if (types.length == 0) {
            return;
          }
          // Device name
          let txt = `
            <div class="row">
              <div class="col-sm">
                <h5 class="mb-2">${desc['friendlyName']}</h5>
              </div>
              <div class="col-sm">
                <div class="float-sm-right">
                  <p id="${getSampleTimeElement(dev.id)}"></p>
                </div>
              </div>
            </div>
            <div class="row">`;

          // Add AQI stat
          if (types.includes("4") || types.includes("5")) {
            txt += `
              <div class="col-md-3">
                <div id="${getAQIElements(dev.id).bg}" class="small-box">
                  <div class="inner">
                    <h3 id="${getAQIElements(dev.id).value}">No data</h3>
                    <p>AQI</p>
                  </div>
                  <div class="icon">
                    <i class="fas fa-heart"></i>
                  </div>
                  <a href="${link}" class="small-box-footer">
                    Data <i class="fas fa-arrow-circle-right"></i>
                  </a>
                </div>
              </div>`;
            aqiDevices.push(dev.id);
          }

          for (let type of types) {
            let displaySet = getSensorDisplaySet(dev.id, Number(type), 0);
            // One box per stat on the same row
            txt += `
              <div class="col-md-3">
                <div id="${displaySet.bgId}" class="small-box bg-${displaySet.bg}">
                  <div class="inner">
                    <h3 id="${displaySet.id}">No data</h3>
                    <p>${displaySet.type}</p>
                  </div>
                  <div class="icon">
                    <i class="fas ${displaySet.icon}"></i>
                  </div>
                  <a href="${link}" class="small-box-footer">
                    Data <i class="fas fa-arrow-circle-right"></i>
                  </a>
                </div>
              </div>`;
          }
          txt += `</div>`;
          $(`#dev${dev.id}`).append(txt);

          updateSensorValue(dev);
        });
    }
  }

  function initStats() {
    /***********************
    /* Weather
    /**********************/

    // Clear everything under insertion point
    $('#weatherInsert').empty();

    $.getJSON("api_db.php?getDeviceIds&types=0,1,2",
      function(devs) {

        // Insert divs for each device so that devices are ordered on page by device Id
        let weatherDevs = [];
        for (let deviceId of devs) {
          allDevs.push( { id: deviceId, movingAverage: null } );
          weatherDevs.push( { id: deviceId, movingAverage: null } );
          $('#weatherInsert').append(`<div id=dev${deviceId}></div>`);
        }

        createSensorLayout(weatherDevs, null, 'weather.php');
      }); // getDeviceIds

    /***********************
    /* Air Quality
    /**********************/
    $('#airqInsert').empty();

    $.getJSON("api_db.php?getDeviceIds&types=3,4,5",
      function(devs) {

        // Insert divs for each device so that devices are ordered on page by device Id
        let airQDevs = [];
        for (let deviceId of devs) {
          allDevs.push( { id: deviceId, movingAverage: 120 } );
          airQDevs.push( { id: deviceId, movingAverage: 120 } );
          $('#airqInsert').append(`<div id=dev${deviceId}></div>`);
        }

        createSensorLayout(airQDevs, 120, 'airquality.php');
      }); // getDeviceIds

    /***********************
    /* Energy
    /**********************/
    $('#meterInsert').empty();

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
                    <p>${statDescOverride.get(stat)}</p>
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
    {
      updateFunc: function() { updateSensorValues() },
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
