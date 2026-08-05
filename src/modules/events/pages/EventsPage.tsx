/**
 * Página principal dos eventos.
 */

import {
  useEffect,
  useState,
} from "react";

import MainLayout
  from "../../../layouts/MainLayout";

import Button
  from "../../../components/ui/Button";

import EventList
  from "../components/EventList";

import EventSearch
  from "../components/EventSearch";

import EventFilters
  from "../components/EventFilters";

import EventModal
  from "../components/EventModal";

import EventSort
  from "../components/EventSort";

import { useFilteredEvents }
  from "../hooks/useFilteredEvents";

import { useEventModal }
  from "../store/useEventModal";

import { useEventStore }
  from "../store/useEventStore";

import { useContactStore }
  from "../../contacts/store/useContactStore";

export default function EventsPage() {
  const [search, setSearch] =
    useState("");

  const [category, setCategory] =
    useState("");

  const [priority, setPriority] =
    useState("");

  const [sortBy, setSortBy] =
    useState("date");

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

  const filteredEvents =
    useFilteredEvents({
      search,
      category,
      priority,
      sortBy,
    });

  const openCreate = useEventModal(
    (state) => state.openCreate
  );

  return (
    <MainLayout
      title="Eventos"
      subtitle="Gerencie seus compromissos"
    >
      <div className="space-y-8">
        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h1 className="text-3xl font-bold text-base-content">
              Seus eventos
            </h1>

            <p className="mt-1 text-base-content/60">
              Organize sua rotina inteligente
            </p>
          </div>

          <Button onClick={openCreate}>
            Novo evento
          </Button>
        </div>

        <div className="space-y-4">
          <EventSearch
            value={search}
            onChange={setSearch}
          />

          <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <EventFilters
              category={category}
              setCategory={setCategory}
              priority={priority}
              setPriority={setPriority}
            />

            <EventSort
              value={sortBy}
              onChange={setSortBy}
            />
          </div>
        </div>

        <EventList events={filteredEvents} />
        <EventModal />
      </div>
    </MainLayout>
  );
}
