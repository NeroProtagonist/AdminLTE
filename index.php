<?php
  require "header.php"
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm">
        <h1 class="m-0 text-dark">Overview</h1>
      </div>
    </div>
  </div>
</div>

<div class="content">
  <div id="insertPoint" class="container-fluid">
    <!-- Insertion place for weather sensors -->

  </div> <!-- .container-fluid -->
</div>

<!-- page script -->
<script>
  "use strict";

  $(document).ready(function() {
    // Get list of devices
    $.getJSON("api_db.php?getDeviceIds",
      function(data) {
        // Insert divs for each device so that devices are ordered on page by device Id
        for (let deviceId of data) {
          $('#insertPoint').append(`<div id=dev${deviceId}></div>`);
        }

        for (let deviceId of data) {
          $.getJSON("api_db.php?getDeviceDesc&deviceId=" + deviceId,
            function(desc) {
              $.getJSON("api_db.php?getLastValues&deviceId=" + deviceId,
                function(values) {
                  let txt = `
                      <h5 class="mb-2">${desc['friendlyName']}</h5>
                      <div class="row">`
                  for (let value of values) {
                    function getDisplaySet(value) {
                      let val = Number(value['value']);
                      switch (Number(value['type'])) {
                        case 0: return { value: val.toFixed(1) + '&#x2103;', type: 'Temperature', bg: 'primary' };
                        case 1: return { value: val.toFixed(0) + '%', type: 'Relative Humidity', bg: 'secondary' };
                        case 2: return { value: val.toFixed(1), type: 'Pressure', bg: 'success' };
                      }
                    }

                    let displaySet = getDisplaySet(value);

                    txt += `
                        <div class="col-md-3">

                          <div class="small-box bg-${displaySet.bg}">
                            <div class="inner">
                              <h3>${displaySet.value}</h3>
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
                });
            });
        }
      });
  });
</script>

<?php
  require "footer.php"
?>
