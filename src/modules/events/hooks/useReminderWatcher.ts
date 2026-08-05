// serc/modules/events/hooks/useReminderWatcher.ts
/**
 * Watcher global de reminders
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */
import { useEffect } from "react";

import { useEventStore }
  from "../store/useEventStore";

import { useNotifications }
  from "../../notifications/hooks/useNotifications";

import {
  shouldTriggerReminder,
} from "../../notifications/services/reminder.service";

export function useReminderWatcher() {
  const events = useEventStore(
    (state) => state.events
  );

  const markReminderAsSent =
    useEventStore(
      (state) =>
        state.markReminderAsSent
    );

  const notifications =
    useNotifications();

  useEffect(() => {
    const interval = setInterval(() => {
      events.forEach((event) => {
        const shouldNotify =
          shouldTriggerReminder(
            event
          );

        if (shouldNotify) {
          notifications.info(
            "Lembrete de evento",
            `${event.title} começa em ${event.reminder} minutos.`
          );

          /**
           * Marca como enviado
           */
          markReminderAsSent(
            event.id
          );
        }
      });
    }, 30000);

    return () =>
      clearInterval(interval);

  }, [
    events,
    notifications,
    markReminderAsSent,
  ]);
}