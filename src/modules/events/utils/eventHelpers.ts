import type {
  Event,
} from "../types/event.types";

/**
 * O modelo atual não possui duração ou horário final.
 * Eventos válidos usam duração visual padrão de 60 minutos.
 */
export function getEventDuration(
  event: Event
): number {
  const start = new Date(
    `${event.date}T${event.time || "00:00"}`
  );

  return Number.isNaN(start.getTime())
    ? 0
    : 60;
}
