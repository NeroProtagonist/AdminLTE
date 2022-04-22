export function getScheme(aqiCat) {
  switch (aqiCat) {
      case 0: return { text: "Good", color: "bg-success" };
      case 1: return { text: "Moderate", color: "bg-warning" };
      case 2: return { text: "USG", color: "bg-orange" };
      case 3: return { text: "Unhealthy", color: "bg-danger" };
      case 4: return { text: "Very Unhealthy", color: "bg-purple" };
      case 5: return { text: "Hazardous", color: "bg-maroon" };
  }
}

export function getAQIBase() {
  const AQIBase = [ 0, 51, 101, 151, 201, 301, 501 ];
  return AQIBase;
}

export function getPM25Base() {
  const pm25Base = [ 0, 15.5, 40.5, 65.5, 150.5, 250.5 ];
  return pm25Base;
}

export function getPM10Base() {
  const pm10Base = [ 0, 55, 155, 255, 355, 425 ];
  return pm10Base;
}

export function getAQI(pmBase, AQIBase, pmObs) {
  let cat = 0;
  for (let i = 1; i < 6; ++i) {
    if (pmObs < pmBase[i]) {
      cat = i - 1;
      break;
    }
  }

  const aqi = ((pmObs - pmBase[cat]) * (AQIBase[cat + 1] - AQIBase[cat])) / (pmBase[cat + 1] - pmBase[cat]) + AQIBase[cat];
  return { aqi: aqi, cat: cat };
}

