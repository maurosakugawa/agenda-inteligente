/**
 * Calendário visual com detalhes integrados.
 */

import {
  useEffect,
  useState,
} from "react";

import {
  Calendar,
  dateFnsLocalizer,
  type View,
} from "react-big-calendar";

import {
  format,
  getDay,
  parse,
  startOfWeek,
} from "date-fns";

import { ptBR }
  from "date-fns/locale";

import "react-big-calendar/lib/css/react-big-calendar.css";

import { useEventStore }
  from "../../events/store/useEventStore";

import { useContactStore }
  from "../../contacts/store/useContactStore";

import EventDetailsModal
  from "../../events/components/EventDetailsModal";

import type { Event }
  from "../../events/types/event.types";

import type { CalendarEvent }
  from "../types/calendar.types";

import { mapEventsToCalendar }
  from "../utils/calendar.utils";

const locales = {
  "pt-BR": ptBR,
};

const localizer = dateFnsLocalizer({
  format,
  parse,
  startOfWeek,
  getDay,
  locales,
});

export default function EventCalendar() {
  const events = useEventStore(
    (state) => state.events
  );

  const eventsLoaded = useEventStore(
    (state) => state.loaded
  );

  const fetchEvents = useEventStore(
    (state) => state.fetchEvents
  );

  const contactsLoaded = useContactStore(
    (state) => state.loaded
  );

  const fetchContacts = useContactStore(
    (state) => state.fetchContacts
  );

  const [view, setView] =
    useState<View>("month");

  const [date, setDate] =
    useState(new Date());

  const [selectedEvent, setSelectedEvent] =
    useState<Event | null>(null);

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

  const calendarEvents =
    mapEventsToCalendar(events);

  return (
    <>
      <div className="rounded-3xl border border-base-300 bg-base-100 p-3 shadow-lg sm:p-6">
        <Calendar<CalendarEvent>
          localizer={localizer}
          events={calendarEvents}
          startAccessor="start"
          endAccessor="end"
          culture="pt-BR"
          view={view}
          onView={(newView) =>
            setView(newView)
          }
          date={date}
          onNavigate={(newDate) =>
            setDate(newDate)
          }
          onSelectEvent={(
            calendarEvent: CalendarEvent
          ) => {
            setSelectedEvent(
              calendarEvent.resource
            );
          }}
          views={{
            month: true,
            week: true,
            day: true,
            agenda: true,
          }}
          defaultView="month"
          selectable
          popup
          style={{ height: 700 }}
          messages={{
            today: "Hoje",
            next: "Próximo",
            previous: "Anterior",
            month: "Mês",
            week: "Semana",
            day: "Dia",
            agenda: "Agenda",
            noEventsInRange:
              "Nenhum evento neste período.",
          }}
        />
      </div>

      <EventDetailsModal
        event={selectedEvent}
        onClose={() =>
          setSelectedEvent(null)
        }
      />
    </>
  );
}
