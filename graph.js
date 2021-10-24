export function makeDefaultGraphOptions() {
  const defaultGraphOptions = {
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
              }
            },
          }
        ],
        yAxes: [{
          ticks: {
            beginAtZero: true
          }
        }]
      },
      animation: false
    };

  return defaultGraphOptions;
}

export function deltaString(startDate, endDate) {
  let millis = endDate.diff(startDate);
  let str = '';
  if (millis >= 2 * 24 * 60 * 60 * 1000) {
    let days = Math.trunc(millis / (24 * 60 * 60 * 1000));
    str += days + 'd';
    millis -= days * 24 * 60 * 60 * 1000;
  }
  if (millis >= 60 * 60 * 1000) {
    let hours = Math.trunc(millis / (60 * 60 * 1000));
    str += hours + 'h';
    millis -= hours * 60 * 60 * 1000;
  }
  if (millis >= 60 * 1000) {
    let minutes = Math.trunc(millis / (60 * 1000));
    str += minutes + 'm';
    millis -= minutes * 60 * 1000;
  }
  if (millis >= 1000) {
    let seconds = Math.trunc(millis / 1000);
    str += seconds + 's';
    millis -= seconds * 1000;
  }
  return str;
}

export function makeDefaultGraphColours() {
  return {
    red: 'rgb(255, 99, 132)',
    orange: 'rgb(255, 159, 64)',
    yellow: 'rgb(255, 205, 86)',
    green: 'rgb(75, 192, 192)',
    blue: 'rgb(54, 162, 235)',
    purple: 'rgb(153, 102, 255)',
    grey: 'rgb(201, 203, 207)'
  };
}