import { apiUrl } from "../../../config/api";

export interface WeatherData {
  city: string;
  temp: number;
  feels_like: number;
  humidity: number;
  wind_speed: number;
  description: string;
  icon: string;
  condition: string;
  isDay: boolean;

  /**
   * Instante do cálculo/observação,
   * em Unix UTC.
   */
  observedAt: number;

  /**
   * Deslocamento da cidade em relação
   * ao UTC, em segundos.
   */
  timezoneOffset: number;

  /**
   * Horário local da observação
   * na cidade, em HH:mm.
   */
  observedTime: string;
}

export interface ForecastItem {
  /**
   * Data civil da cidade.
   * Formato YYYY-MM-DD.
   */
  date: string;
  temp: number;
  description: string;
  icon: string;
  condition: string;
}

export type EventForecastUnavailableReason =
  | "past"
  | "outside-window"
  | "not-found";

export type EventForecastSelection =
  | "nearest"
  | "next"
  | "noon";

export interface EventForecastAvailable {
  status: "available";
  city: string;
  date: string;
  time: string;
  temp: number;
  description: string;
  icon: string;
  condition: string;
  selection:
    EventForecastSelection;
}

export interface EventForecastUnavailable {
  status: "unavailable";
  reason:
    EventForecastUnavailableReason;
}

export type EventForecastResult =
  | EventForecastAvailable
  | EventForecastUnavailable;

async function fetchFromBackend<T>(
  path: string
): Promise<T> {
  const response =
    await fetch(
      apiUrl(path),
      {
        credentials: "include",
        headers: {
          Accept:
            "application/json",
        },
      }
    );

  if (response.status === 401) {
    throw new Error(
      "Não autenticado"
    );
  }

  if (!response.ok) {
    const body =
      await response
        .json()
        .catch(() => null);

    throw new Error(
      typeof body?.error ===
      "string"
        ? body.error
        : "Erro ao consultar o clima"
    );
  }

  return (
    await response.json()
  ) as T;
}

function cityQuery(
  city: string
): string {
  const cleanCity =
    city.trim();

  if (!cleanCity) {
    throw new Error(
      "Informe uma cidade."
    );
  }

  return encodeURIComponent(
    cleanCity
  );
}

/**
 * Clima atual.
 *
 * A chave e o cache de 30 minutos ficam
 * exclusivamente no backend.
 */
export function fetchWeather(
  city: string
): Promise<WeatherData> {
  return fetchFromBackend<WeatherData>(
    `/api/weather/current?city=${cityQuery(
      city
    )}`
  );
}

/**
 * Previsão diária próxima de 12h.
 *
 * O backend compartilha o cache bruto desta
 * previsão com os badges dos eventos.
 */
export function fetchForecast(
  city: string
): Promise<ForecastItem[]> {
  return fetchFromBackend<
    ForecastItem[]
  >(
    `/api/weather/forecast?city=${cityQuery(
      city
    )}`
  );
}

/**
 * Previsão do intervalo de três horas mais
 * adequado ao horário do evento.
 */
export function fetchForecastForEvent(
  city: string,
  eventDate: string,
  eventTime?: string
): Promise<EventForecastResult> {
  const params =
    new URLSearchParams({
      city: city.trim(),
      date: eventDate,
    });

  if (eventTime) {
    params.set(
      "time",
      eventTime
    );
  }

  return fetchFromBackend<
    EventForecastResult
  >(
    `/api/weather/event?${params.toString()}`
  );
}
