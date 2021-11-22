<?php

function newChart($id, $heading)
{
    $html = <<<HTML
        <div class="row">
          <div class="col-md">
            <!-- Main chart -->
            <div class="card card-primary" id="$id-graph">
              <div class="card-header">
                <h3 class="card-title">$heading</h3>
                  <div class="card-tools"> <!-- TODO: Probably unneeded -->
                    <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-minus"></i>
                    </button>
                </div>
              </div>
              <div class="card-body">
                <div class="chart">
                  <canvas id="$id-graph-element" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                </div>
              </div> <!-- /.card-body -->
              <div class="overlay">
                <i class="fas fa-2x fa-sync-alt fa-spin"></i>
              </div> <!-- /.overlay -->
            </div> <!-- /.card -->
          </div>
        </div> <!-- row -->
        HTML;

    echo $html;
}

?>