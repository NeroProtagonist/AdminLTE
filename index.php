<?php
  require "header.php"
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1 class="m-0 text-dark">Overview</h1>
      </div>
      <div class="col-sm-6">
        <div class="float-sm-right">
          <input type="checkbox" name="refreshStats" id="refreshStatsToggle">
          <label for="refreshStatsToggle">Refresh</label>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="content">
  <div id="insertPoint" class="container-fluid">
    <!-- Insertion place for weather sensors -->

  </div> <!-- .container-fluid -->
</div>

<!-- Bootstrap Switch -->
<script src="../../plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>

<!-- page script -->
<script>
  "use strict";

  let statsRefresh;

  let devs = [];

  function initStats() {
    $.getJSON("api_db.php?getDeviceIds",
      function(data) {
        // Clear everything under insertion point
        $('#insertPoint').empty();

        // Insert divs for each device so that devices are ordered on page by device Id
        for (let deviceId of data) {
          $('#insertPoint').append(`<div id=dev${deviceId}></div>`);
        }

        devs = data;

        for (let deviceId of devs) {
          $.getJSON("api_db.php?getDeviceDesc&deviceId=" + deviceId,
            function(desc) {
              $.getJSON("api_db.php?getLastValues&deviceId=" + deviceId,
                function(values) {
                  // Device name
                  let txt = `
                    <h5 class="mb-2">${desc['friendlyName']}</h5>
                    <div class="row">`;
                  for (let value of values) {
                    let displaySet = getDisplaySet(deviceId, value);
                    // One card per stat on the same row
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
                }); // getLastValues
            }); // getDeviceDesc
        }
      }); // getDeviceIds
  }

  function getDisplaySet(deviceId, value) {
    let val = Number(value['value']);
    switch (Number(value['type'])) {
      case 0: return { id: "device" + deviceId + "_type" + value['type'], value: val.toFixed(1) + '&#x2103;', type: 'Temperature', bg: 'primary' };
      case 1: return { id: "device" + deviceId + "_type" + value['type'], value: val.toFixed(0) + '%', type: 'Relative Humidity', bg: 'secondary' };
      case 2: return { id: "device" + deviceId + "_type" + value['type'], value: val.toFixed(1), type: 'Pressure', bg: 'success' };
    }
  }

  function updateStats() {
    for (let deviceId of devs) {
      $.getJSON("api_db.php?getLastValues&deviceId=" + deviceId,
        function(values) {
          for (let value of values) {
            let displaySet = getDisplaySet(deviceId, value);
            $(`#${displaySet.id}`).html(displaySet.value);
          }
        });
    }
  }

  $(document).ready(function() {

    $("[name='refreshStats'").bootstrapSwitch();
    $("[name='refreshStats'").on('switchChange.bootstrapSwitch', function(event, state) {
      if (state) {
        statsRefresh = setInterval(updateStats, 5000);
      } else {
        clearInterval(statsRefresh);
      }
    });

    initStats();
  });
</script>

<?php
  require "footer.php"
?>
