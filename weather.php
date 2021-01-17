<?php
  require "header.php"
?>

<div class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm">
        <h1 class="m-0 text-dark">Weather</h1>
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
        <div class="card card-primary" id="weather-graph">
          <div class="card-header">
            <h3 class="card-title">Weather</h3>
              <div class="card-tools"> <!-- TODO: Probably unneeded -->
                <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                </button>
            </div>
          </div>
          <div class="card-body">
            <div class="chart">
              <canvas id="mainChartElement" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
            </div>
          </div> <!-- /.card-body -->
          <div class="overlay">
            <i class="fas fa-2x fa-sync-alt fa-spin"></i>
          </div> <!-- /.overlay -->
        </div> <!-- /.card -->
      </div>
    </div>
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
<script>
  var mainChart;

  $(function () {
    var mainChartCanvas = $('#mainChartElement').get(0).getContext('2d')
    var mainChartOptions = {
      maintainAspectRatio: false,
      responsive: true,
      datasetFill: false,
      scales: {
        xAxes: [
          {
            type: 'time',
            time: {
              minUnit: 'minute',
              displayFormats: {
                second: 'HH:mm:ss',
                minute: 'HH:mm',
                hour: 'HH'
              },
              ticks: {
                //sampleSize: 20 Doesn't work???
              }
            }
          }
        ],
        yAxes: [{
          ticks: {
            beginAtZero: true
          }
        }]
      }
    }

    window.chartColors = {
      red: 'rgb(255, 99, 132)',
      orange: 'rgb(255, 159, 64)',
      yellow: 'rgb(255, 205, 86)',
      green: 'rgb(75, 192, 192)',
      blue: 'rgb(54, 162, 235)',
      purple: 'rgb(153, 102, 255)',
      grey: 'rgb(201, 203, 207)'
    };

    mainChart = new Chart(mainChartCanvas, {
      type: 'line',
      data: {
        datasets: [
          {
            label: 'Temperature',
            backgroundColor: window.chartColors.red,
            borderColor: window.chartColors.red,
            fill: false,
            data: []
          }
        ]
      },
      options: mainChartOptions
    });

    $('#querytime').daterangepicker(
      {
        timePicker: true,
        timePickerIncrement: 15,
        timePicker24Hour: true,
        locale: {
          format: "DD/MM/YYYY HH:MM"
        },
        ranges : {
          'Last 5 minutes'  : [moment().subtract(5, 'minutes'), moment()],
          'Last 30 minutes' : [moment().subtract(30, 'minutes'), moment()],
          'Today'       : [moment().startOf('day'), moment()],
          'Yesterday'   : [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
          'Last 7 Days' : [moment().subtract(6, 'days'), moment()],
          'Last 30 Days': [moment().subtract(29, 'days'), moment()],
          'This Month'  : [moment().startOf('month'), moment().endOf('month')],
          'This Year'   : [moment().startOf("year"), moment()],
          'All Time'    : [moment(0), moment()]
        },
        startDate: moment().subtract(24, 'hours'), // Default
        endDate: moment(),
        opens: 'center',
        autoUpdateInput: true
      },
      /*function (start, end) {
        //$('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'))
        //TODO: Start query
      }*/
    )

    // Initial fetch
    var picker = $('#querytime').data('daterangepicker');
    fetchAndUpdate(picker.startDate, picker.endDate);
  });

  $("#querytime").on("apply.daterangepicker", function (ev, picker) {
    //$(this).val(picker.startDate.format(dateformat) + " to " + picker.endDate.format(dateformat));
    fetchAndUpdate(picker.startDate, picker.endDate);
  });

  function fetchAndUpdate(startDate, endDate) {
    $('#weather-graph .overlay').show();
    var startUTC = Math.trunc(startDate.valueOf() / 1000);
    var endUTC = Math.trunc(endDate.valueOf() / 1000);
    $.getJSON("api_db.php?getGraphData&weather&from=" + startUTC + "&to=" + endUTC,
      function (data) {
        var samples = [];
        var labels = [];
        var totalNum = 0;
        $.each(data, function(timestamp_s, record) {
          ++totalNum;
          if (record['type'] != 0)
          {
            return;
          }
          if (samples.length > 200) {
            return;
          }
          var dateNum = Number(timestamp_s * 1000);
          samples.push(record['value']);
          labels.push(new Date(Number(timestamp_s * 1000)));
        });

        console.log("Got " + totalNum + " records");

        mainChart.data.datasets[0].data = samples;
        mainChart.data.labels = labels;
        mainChart.update();
        $('#weather-graph .overlay').hide();
      });
  }
</script>

<?php
  require "footer.php"
?>
