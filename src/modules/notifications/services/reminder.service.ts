// src/modules/notifications/services/reminder_service.ts
/**
 * Serviço de reminders automáticos
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import type {
  Event,
} from "../../events/types/event.types";

export function shouldTriggerReminder(
  event: Event
) {
  /**
   * Evita duplicação
   */
  if (event.reminderSent) {
    return false;
  }

  const eventDate = new Date(
    `${event.date}T${event.time}`
  );

  const now = new Date();

  const diffMs =
    eventDate.getTime() -
    now.getTime();

  const diffMinutes =
    Math.floor(diffMs / 60000);

  return (
    diffMinutes <= event.reminder &&
    diffMinutes >= 0
  );
}