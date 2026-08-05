import {
  useCallback,
  useEffect,
  useMemo,
  useRef,
  useState,
} from "react";

import { useCurrentTime }
  from "../../../hooks/useCurrentTime";

import { Cloud }
  from "lucide-react";

import CitySearch
  from "../components/CitySearch";

import WeatherCard
  from "../components/WeatherCard";

import ForecastCard
  from "../components/ForecastCard";

import WeatherEffects
  from "../effects/WeatherEffects";

import {
  fetchForecast,
  fetchWeather,
  type ForecastItem,
  type WeatherData,
} from "../services/weatherService";

import { getWeatherTheme }
  from "../themes/weatherTheme";

import { useEventStore }
  from "../../events/store/useEventStore";

const LAST_WEATHER_CITY_KEY =
  "smart-planner:last-weather-city";



function getEventTimestamp(
  date: string,
  time: string
): number {
  const timestamp = new Date(
    `${date}T${time || "00:00"}`
  ).getTime();

  return Number.isNaN(timestamp)
    ? Number.MAX_SAFE_INTEGER
    : timestamp;
}

export default function WeatherPage() {
  const events = useEventStore(
    (state) => state.events
  );

  const [weather, setWeather] =
    useState<WeatherData | null>(null);

  const [forecast, setForecast] =
    useState<ForecastItem[]>([]);

  const [loading, setLoading] =
    useState(false);

  const [error, setError] =
    useState("");

  const [activeCity, setActiveCity] =
    useState("");

  const attemptedCitiesRef = useRef(
    new Set<string>()
  );

  const currentTime =
    useCurrentTime();

  const nextEventCity = useMemo(() => {
    const eventsWithLocation = events
      .filter((event) =>
        Boolean(event.location?.trim())
      )
      .map((event) => ({
        event,
        timestamp: getEventTimestamp(
          event.date,
          event.time
        ),
      }))
      .sort(
        (first, second) =>
          first.timestamp - second.timestamp
      );

    const nextEvent =
      eventsWithLocation.find(
        ({ timestamp }) =>
          timestamp >= currentTime
      ) || eventsWithLocation[0];

    return nextEvent?.event.location?.trim() || "";
  }, [
    currentTime,
    events,
  ]);


  const handleSearch = useCallback(
    async (city: string) => {
      const normalizedCity = city.trim();

      if (!normalizedCity) {
        return;
      }

      setLoading(true);
      setError("");
      setActiveCity(normalizedCity);

      try {
        const [weatherData, forecastData] =
          await Promise.all([
            fetchWeather(normalizedCity),
            fetchForecast(normalizedCity),
          ]);

        setWeather(weatherData);
        setForecast(forecastData);

        const resolvedCity =
          weatherData.city || normalizedCity;

        setActiveCity(resolvedCity);

        window.localStorage.setItem(
          LAST_WEATHER_CITY_KEY,
          resolvedCity
        );
      } catch {
        setError(
          `Não foi possível carregar o clima de ${normalizedCity}. Confira o nome da cidade.`
        );
        setWeather(null);
        setForecast([]);
      } finally {
        setLoading(false);
      }
    },
    []
  );

  useEffect(() => {
    if (loading || weather) {
      return;
    }

    const savedCity =
      window.localStorage
        .getItem(LAST_WEATHER_CITY_KEY)
        ?.trim() || "";

    const candidates = [
      savedCity,
      nextEventCity,
    ].filter(Boolean);

    const cityToLoad = candidates.find(
      (candidate) => {
        const key = candidate.toLocaleLowerCase(
          "pt-BR"
        );

        return !attemptedCitiesRef.current.has(
          key
        );
      }
    );

    if (!cityToLoad) {
      return;
    }

    attemptedCitiesRef.current.add(
      cityToLoad.toLocaleLowerCase("pt-BR")
    );

    void handleSearch(cityToLoad);
  }, [
    handleSearch,
    loading,
    nextEventCity,
    weather,
  ]);

  const theme = weather
    ? getWeatherTheme(
        weather.condition,
        weather.isDay
      )
    : null;

  const citySearchInitialValue =
    activeCity || nextEventCity;


  return (
    <div
      className={`relative min-h-[calc(100vh-12rem)] overflow-hidden rounded-3xl transition-all duration-700 ${
        theme
          ? `bg-gradient-to-br ${theme.bgGradient}`
          : "bg-gradient-to-br from-blue-400 to-blue-600"
      }`}
    >
      {weather && (
        <WeatherEffects
          type={
            theme?.particleType || "none"
          }
        />
      )}

      <div className="container relative z-10 mx-auto px-4 py-8 sm:px-6">
        <div className="mb-8 flex items-center gap-3">
          <Cloud className="h-8 w-8" />
          <div>
            <h1 className="text-3xl font-bold">
              Clima
            </h1>
            <p className="text-sm opacity-70">
              A última cidade consultada é lembrada automaticamente.
            </p>
          </div>
        </div>

        <div className="mb-8 flex justify-center">
          <CitySearch
            key={
              citySearchInitialValue
              || 'empty-city'
            }
            onSearch={handleSearch}
            loading={loading}
            initialCity={
              citySearchInitialValue
            }
          />
        </div>

        {loading && !weather && (
          <div className="mx-auto mt-16 flex max-w-md flex-col items-center gap-4 text-center">
            <span className="loading loading-spinner loading-lg" />
            <p className="text-lg">
              Carregando clima
              {activeCity
                ? ` de ${activeCity}`
                : ""}
              ...
            </p>
          </div>
        )}

        {error && (
          <div className="alert alert-error mx-auto mb-6 max-w-xl">
            <span>{error}</span>
          </div>
        )}

        {weather && (
          <div className="mx-auto grid max-w-4xl gap-6 pb-6">
            <WeatherCard data={weather} />
            <ForecastCard
              forecast={forecast}
            />
          </div>
        )}

        {!weather &&
          !loading &&
          !error && (
            <div className="mt-20 text-center opacity-70">
              <Cloud className="mx-auto mb-4 h-20 w-20" />
              <p className="text-xl">
                Busque uma cidade para ver o clima
              </p>
              {nextEventCity && (
                <p className="mt-2 text-sm">
                  Cidade sugerida pelo próximo evento: {nextEventCity}
                </p>
              )}
            </div>
          )}
      </div>
    </div>
  );
}
