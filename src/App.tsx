/**
 * Aplicação principal.
 *
 * As stores são carregadas somente depois da autenticação e são limpas no
 * logout. Isso evita que dados de um usuário permaneçam visíveis para outro.
 */

import { useEffect } from "react";

import AppRoutes from "./routes/AppRoutes";

import NotificationContainer
  from "./modules/notifications/components/NotificationContainer";

import { useReminderWatcher }
  from "./modules/events/hooks/useReminderWatcher";

import { useEventStore }
  from "./modules/events/store/useEventStore";

import { useContactStore }
  from "./modules/contacts/store/useContactStore";

import { useAuthStore }
  from "./store/useAuthStore";

export default function App() {
  useReminderWatcher();

  const user = useAuthStore(
    (state) => state.user
  );

  const authInitialized = useAuthStore(
    (state) => state.initialized
  );

  const fetchEvents = useEventStore(
    (state) => state.fetchEvents
  );

  const resetEvents = useEventStore(
    (state) => state.reset
  );

  const fetchContacts = useContactStore(
    (state) => state.fetchContacts
  );

  const resetContacts = useContactStore(
    (state) => state.reset
  );

  useEffect(() => {
    if (!authInitialized) {
      return;
    }

    if (user) {
      void Promise.all([
        fetchEvents(),
        fetchContacts(),
      ]);

      return;
    }

    resetEvents();
    resetContacts();
  }, [
    authInitialized,
    user,
    fetchEvents,
    fetchContacts,
    resetEvents,
    resetContacts,
  ]);

  return (
    <>
      <AppRoutes />
      <NotificationContainer />
    </>
  );
}
