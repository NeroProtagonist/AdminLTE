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
  <div class="container-fluid">
    <div class="row">
      <div class="col-md">
        <!-- Temp 24 hours -->
        <div class="card card-primary">
          <div class="card-header">
            <h3 class="card-title">Temperature over 24 hours</h3>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="temp24Chart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div>
        </div> <!-- .card -->
      </div>
    </div>
  </div> <!-- .container-fluid -->
</div>

<!-- ChartJS -->
<script src="plugins/chart.js/Chart.min.js"></script>
<!--<script src="index.js"></script>-->

<?php
  require "footer.php"
?>
