import {
  Calendar,
} from "lucide-react";

import type {
  ForecastItem,
} from "../services/weatherService";

interface Props {
  forecast: ForecastItem[];
}

/**
 * Interpreta YYYY-MM-DD como data civil local.
 *
 * Não usamos new Date("YYYY-MM-DD"), pois essa forma é interpretada como UTC
 * e, em fusos negativos como o Brasil, pode aparecer como o dia anterior.
 */
function formatDate(
  dateString: string
): string {
  const [year, month, day] =
    dateString.split("-").map(Number);

  const date =
    new Date(year, month - 1, day);

  return date.toLocaleDateString(
    "pt-BR",
    {
      weekday: "short",
      day: "numeric",
      month: "short",
    }
  );
}

export default function ForecastCard({
  forecast,
}: Props) {
  return (
    <div className="card bg-base-100/80 backdrop-blur shadow-xl">
      <div className="card-body">
        <h3 className="card-title flex items-center gap-2">
          <Calendar className="w-5 h-5" />
          Previsão 5 dias
        </h3>

        <p className="text-xs text-base-content/60">
          Temperatura prevista para o período mais próximo de 12h,
          no horário local da cidade.
        </p>

        <div className="grid grid-cols-2 md:grid-cols-5 gap-3 mt-4">
          {forecast.map((day) => (
            <div
              key={day.date}
              className="flex flex-col items-center p-3 rounded-lg bg-base-200/50"
            >
              <span className="text-sm text-base-content/60">
                {formatDate(day.date)}
              </span>

              <img
                src={`https://openweathermap.org/img/wn/${day.icon}@2x.png`}
                alt={day.description}
                className="w-12 h-12"
              />

              <span className="text-xl font-bold">
                {day.temp}°
              </span>

              <span className="text-xs capitalize text-center">
                {day.description}
              </span>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
