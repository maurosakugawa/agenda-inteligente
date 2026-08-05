export interface WeatherData {
  name: string;

  timezone: number;

  dt: number;

  sys: {
    sunrise: number;
    sunset: number;
  };

  main: {
    temp: number;
    feels_like: number;
    humidity: number;
  };

  weather: {
    main: string;
    description: string;
    icon: string;
  }[];

  wind: {
    speed: number;
  };

}