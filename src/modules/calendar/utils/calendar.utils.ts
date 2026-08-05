import type { Event }
  from "../../events/types/event.types";

import type { CalendarEvent }
  from "../types/calendar.types";

export function mapEventsToCalendar(
  events: Event[]
): CalendarEvent[] {
  return events.map((event) => {
    const safeTime =
      event.time || "00:00";

    const start = new Date(
      `${event.date}T${safeTime}`
    );

    const end = new Date(
      start.getTime() + 60 * 60 * 1000
    );

    return {
      title: event.title,
      start,
      end,
      resource: event,
    };
  });
}
