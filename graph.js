export class Graph {
  constructor(type, prefix, label) {
    this.type = type;
    this.prefix = prefix;
    this.label = label;
    this.options = JSON.parse(JSON.stringify(this.makeDefaultGraphOptions()));
  }

  getElement() {
    return `${this.prefix}-graph-element`;
  }

  getCardId() {
    return `${this.prefix}-graph`;
  }

  makeDefaultGraphOptions() {
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
                hour: 'HH:mm'
              }
            }
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

};

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

export function makeDefaultTimePickerOptions() {
  return {
    timePicker: true,
    timePickerIncrement: 15,
    timePicker24Hour: true,
    locale: {
      format: "DD/MM/YYYY HH:mm"
    },
    ranges : {
      'Last 5 minutes'  : [moment().subtract(5, 'minutes'), moment()],
      'Last hour' : [moment().subtract(1, 'hours'), moment()],
      'Last 6 hours' : [ moment().subtract(6, 'hours'), moment()],
      'Today'       : [moment().startOf('day'), moment()],
      'Last 24 hours' : [moment().subtract(24, 'hours'), moment()],
      'Yesterday'   : [moment().subtract(1, 'days').startOf('day'), moment().subtract(1, 'days').endOf('day')],
      'Last 48 hours' : [moment().subtract(48, 'hours'), moment()],
      'Last 7 Days' : [moment().subtract(7, 'days'), moment()],
      'Last 30 Days': [moment().subtract(29, 'days'), moment()],
      'This Month'  : [moment().startOf('month'), moment().endOf('month')],
      'This Year'   : [moment().startOf("year"), moment()],
      'All Time'    : [moment(0), moment()]
    },
    startDate: moment().subtract(1, 'hours'), // Default
    endDate: moment(),
    opens: 'center',
    autoUpdateInput: false
  };
}

export function getSeconds(unit) {
  switch (unit) {
    case 'second': return 1;
    case 'minute': return 60;
    case 'hour': return 60 * 60;
    case 'day': return 60 * 60 * 24;
  }
  return null;
}
