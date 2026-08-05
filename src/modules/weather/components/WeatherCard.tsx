import { Droplets, Wind, Thermometer, Sun, Moon } from 'lucide-react';
import type { WeatherData } from '../services/weatherService';

interface Props {
  data: WeatherData;
}

export default function WeatherCard({ data }: Props) {
  const iconUrl = `https://openweathermap.org/img/wn/${data.icon}@4x.png`;

  return (
    <div className="card bg-base-100/80 backdrop-blur shadow-xl">
      <div className="card-body items-center text-center">
        <h2 className="card-title text-3xl flex items-center gap-2">
          {data.isDay ? <Sun className="w-8 h-8 text-amber-500" /> : <Moon className="w-8 h-8 text-indigo-400" />}
          {data.city}
        </h2>

        <div className="flex items-center gap-4 my-4">
          <img src={iconUrl} alt={data.description} className="w-24 h-24" />
          <div>
            <p className="text-6xl font-bold">{data.temp}°</p>
            <p className="text-lg capitalize">{data.description}</p>
          </div>
        </div>

        <div className="grid grid-cols-3 gap-4 w-full">
          <div className="flex flex-col items-center p-3 rounded-lg bg-base-200/50">
            <Thermometer className="w-5 h-5 mb-1" />
            <span className="text-sm text-gray-500">Sensação</span>
            <span className="font-semibold">{data.feels_like}°</span>
          </div>
          <div className="flex flex-col items-center p-3 rounded-lg bg-base-200/50">
            <Droplets className="w-5 h-5 mb-1" />
            <span className="text-sm text-gray-500">Umidade</span>
            <span className="font-semibold">{data.humidity}%</span>
          </div>
          <div className="flex flex-col items-center p-3 rounded-lg bg-base-200/50">
            <Wind className="w-5 h-5 mb-1" />
            <span className="text-sm text-gray-500">Vento</span>
            <span className="font-semibold">{data.wind_speed} m/s</span>
          </div>
        </div>
      </div>
    </div>
  );
}
