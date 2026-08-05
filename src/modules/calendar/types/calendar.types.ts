import type { Event }
  from "../../events/types/event.types";

export interface CalendarEvent {
  title: string;
  start: Date;
  end: Date;
  resource: Event;
}
