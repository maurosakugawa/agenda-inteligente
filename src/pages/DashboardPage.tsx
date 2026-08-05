import {
  useEffect,
  useMemo,
  useState,
} from "react";

import {
  CalendarDays,
  Cloud,
  Loader2,
  MapPin,
  Users,
} from "lucide-react";

import { Link } from "react-router-dom";

import MainLayout
  from "../layouts/MainLayout";

import { useCurrentTime }
  from "../hooks/useCurrentTime";

import { useEventStore }
  from "../modules/events/store/useEventStore";

import { useContactStore }
  from "../modules/contacts/store/useContactStore";

import { fetchWeather }
  from "../modules/weather/services/weatherService";

import type { WeatherData }
  from "../modules/weather/services/weatherService";

import EventWeatherBadge
  from "../modules/events/components/EventWeatherBadge";

import EventParticipants
  from "../modules/events/components/EventParticipants";

const LAST_WEATHER_CITY_KEY =
  "smart-planner:last-weather-city";

interface WeatherRequestState {
  city: string;
  data: WeatherData | null;
  error: string;
}

function eventTimestamp(
  date: string,
  time: string
): number {
  return new Date(
    `${date}T${time || "00:00"}`
  ).getTime();
}

export default function DashboardPage() {
  const events = useEventStore(
    (state) => state.events
  );

  const eventsLoaded = useEventStore(
    (state) => state.loaded
  );

  const eventsLoading = useEventStore(
    (state) => state.loading
  );

  const fetchEvents = useEventStore(
    (state) => state.fetchEvents
  );

  const contacts = useContactStore(
    (state) => state.contacts
  );

  const contactsLoaded = useContactStore(
    (state) => state.loaded
  );

  const contactsLoading = useContactStore(
    (state) => state.loading
  );

  const fetchContacts = useContactStore(
    (state) => state.fetchContacts
  );

  const [
    weatherRequest,
    setWeatherRequest,
  ] = useState<WeatherRequestState>({
    city: "",
    data: null,
    error: "",
  });

  useEffect(() => {
    if (!eventsLoaded) {
      void fetchEvents();
    }

    if (!contactsLoaded) {
      void fetchContacts();
    }
  }, [
    eventsLoaded,
    contactsLoaded,
    fetchEvents,
    fetchContacts,
  ]);

  const currentTime =
    useCurrentTime();

  const upcomingEvents =
    useMemo(() => {
      return [...events]
        .filter(
          (event) =>
            eventTimestamp(
              event.date,
              event.time
            ) >= currentTime
        )
        .sort(
          (first, second) =>
            eventTimestamp(
              first.date,
              first.time
            ) -
            eventTimestamp(
              second.date,
              second.time
            )
        )
        .slice(0, 5);
    }, [
      currentTime,
      events,
    ]);

  const recentContacts =
    useMemo(
      () =>
        [...contacts]
          .sort(
            (first, second) =>
              new Date(
                second.created_at
              ).getTime() -
              new Date(
                first.created_at
              ).getTime()
          )
          .slice(0, 5),
      [contacts]
    );

  const weatherCity =
    useMemo(() => {
      const saved =
        window.localStorage.getItem(
          LAST_WEATHER_CITY_KEY
        );

      if (saved?.trim()) {
        return saved.trim();
      }

      const eventLocation =
        upcomingEvents.find(
          (event) =>
            event.location.trim()
        )?.location;

      return eventLocation || "São Paulo";
    }, [upcomingEvents]);

  const weatherRequestIsCurrent =
    weatherRequest.city === weatherCity;

  const weather =
    weatherRequestIsCurrent
      ? weatherRequest.data
      : null;

  const weatherLoading =
    !weatherRequestIsCurrent;

  const weatherError =
    weatherRequestIsCurrent
      ? weatherRequest.error
      : "";

  useEffect(() => {
    let active = true;

    void fetchWeather(weatherCity)
      .then((data) => {
        if (active) {
          setWeatherRequest({
            city: weatherCity,
            data,
            error: "",
          });
        }
      })
      .catch(() => {
        if (active) {
          setWeatherRequest({
            city: weatherCity,
            data: null,
            error:
              "Não foi possível consultar o clima.",
          });
        }
      });

    return () => {
      active = false;
    };
  }, [weatherCity]);

  return (
    <MainLayout
      title="Dashboard"
      subtitle="Visão unificada da sua rotina"
    >
      <div className="grid gap-6 xl:grid-cols-3">
        <section className="card border border-base-300 bg-base-100 shadow-sm xl:col-span-2">
          <div className="card-body">
            <div className="flex items-center justify-between gap-4">
              <h2 className="card-title">
                <CalendarDays size={22} />
                Próximos eventos
              </h2>

              <Link
                to="/events"
                className="btn btn-sm btn-ghost"
              >
                Ver todos
              </Link>
            </div>

            {eventsLoading && !eventsLoaded ? (
              <div className="flex items-center gap-2 py-8 text-base-content/60">
                <Loader2
                  className="animate-spin"
                  size={20}
                />
                Carregando eventos...
              </div>
            ) : upcomingEvents.length ? (
              <div className="mt-3 divide-y divide-base-300">
                {upcomingEvents.map(
                  (event) => (
                    <article
                      key={event.id}
                      className="grid gap-3 py-4 md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                    >
                      <div className="min-w-0">
                        <Link
                          to="/calendar"
                          className="font-semibold hover:text-primary"
                        >
                          {event.title}
                        </Link>

                        <p className="mt-1 text-sm text-base-content/60">
                          {event.date}
                          {event.time
                            ? ` às ${event.time}`
                            : ""}
                        </p>

                        {event.location && (
                          <p className="mt-1 flex items-center gap-1 text-sm text-base-content/60">
                            <MapPin size={14} />
                            {event.location}
                          </p>
                        )}

                        {event.contactIds.length > 0 && (
                          <div className="mt-3">
                            <EventParticipants
                              contactIds={
                                event.contactIds
                              }
                              maxVisible={3}
                              showNames={false}
                            />
                          </div>
                        )}
                      </div>

                      <EventWeatherBadge
                        location={event.location}
                        eventDate={event.date}
                        eventTime={event.time}
                        compact
                      />
                    </article>
                  )
                )}
              </div>
            ) : (
              <div className="mt-4 rounded-box bg-base-200 p-6 text-center text-base-content/60">
                Nenhum evento futuro.
              </div>
            )}
          </div>
        </section>

        <section className="card border border-base-300 bg-base-100 shadow-sm">
          <div className="card-body">
            <div className="flex items-center justify-between gap-3">
              <h2 className="card-title">
                <Cloud size={22} />
                Clima atual
              </h2>

              <Link
                to="/weather"
                className="btn btn-sm btn-ghost"
              >
                Abrir
              </Link>
            </div>

            {weatherLoading ? (
              <div className="flex items-center gap-2 py-8 text-base-content/60">
                <Loader2
                  className="animate-spin"
                  size={20}
                />
                Consultando...
              </div>
            ) : weather ? (
              <div className="mt-5">
                <div className="flex items-center gap-3">
                  <img
                    src={`https://openweathermap.org/img/wn/${weather.icon}@2x.png`}
                    alt=""
                    aria-hidden="true"
                    className="h-16 w-16"
                  />

                  <div>
                    <strong className="text-4xl">
                      {weather.temp}°C
                    </strong>
                    <p className="capitalize text-base-content/60">
                      {weather.description}
                    </p>
                  </div>
                </div>

                <p className="mt-5 flex items-center gap-2">
                  <MapPin size={16} />
                  {weather.city}
                </p>

                <div className="stats stats-vertical mt-5 w-full bg-base-200 shadow-none">
                  <div className="stat py-3">
                    <div className="stat-title">
                      Sensação
                    </div>
                    <div className="stat-value text-xl">
                      {weather.feels_like}°C
                    </div>
                  </div>

                  <div className="stat py-3">
                    <div className="stat-title">
                      Umidade
                    </div>
                    <div className="stat-value text-xl">
                      {weather.humidity}%
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="alert alert-warning mt-5">
                <span>
                  {weatherError ||
                    "Clima indisponível."}
                </span>
              </div>
            )}

            <p className="mt-3 text-xs text-base-content/50">
              Cidade usada: última busca; na ausência, local do próximo evento ou São Paulo.
            </p>
          </div>
        </section>

        <section className="card border border-base-300 bg-base-100 shadow-sm xl:col-span-3">
          <div className="card-body">
            <div className="flex items-center justify-between gap-4">
              <h2 className="card-title">
                <Users size={22} />
                Contatos recentes
              </h2>

              <Link
                to="/contacts"
                className="btn btn-sm btn-ghost"
              >
                Gerenciar
              </Link>
            </div>

            {contactsLoading &&
            !contactsLoaded ? (
              <div className="flex items-center gap-2 py-8 text-base-content/60">
                <Loader2
                  className="animate-spin"
                  size={20}
                />
                Carregando contatos...
              </div>
            ) : recentContacts.length ? (
              <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                {recentContacts.map(
                  (contact) => (
                    <Link
                      key={contact.id}
                      to="/contacts"
                      className="rounded-box border border-base-300 p-4 transition hover:-translate-y-0.5 hover:border-primary hover:shadow-md"
                    >
                      <div className="avatar placeholder">
                        <div className="w-11 rounded-full bg-primary text-primary-content">
                          <span>
                            {contact.name
                              .slice(0, 2)
                              .toUpperCase()}
                          </span>
                        </div>
                      </div>

                      <strong className="mt-3 block truncate">
                        {contact.name}
                      </strong>

                      <span className="mt-1 block truncate text-sm text-base-content/60">
                        {contact.email ||
                          contact.phone ||
                          "Sem telefone/e-mail"}
                      </span>
                    </Link>
                  )
                )}
              </div>
            ) : (
              <div className="mt-4 rounded-box bg-base-200 p-6 text-center text-base-content/60">
                Nenhum contato cadastrado.
              </div>
            )}
          </div>
        </section>
      </div>
    </MainLayout>
  );
}
