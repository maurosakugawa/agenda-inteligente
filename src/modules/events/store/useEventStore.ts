/**
 * Store de eventos.
 *
 * Zustand funciona como cache reativo em memória.
 * O backend/PGlite é a fonte da verdade.
 */

import { create } from "zustand";

import { eventApiService }
  from "../services/eventApi.service";

import type { Event }
  from "../types/event.types";

interface EventStore {
  events: Event[];
  loading: boolean;
  loaded: boolean;
  error: string;

  hydrateEvents: () => Promise<void>;
  fetchEvents: () => Promise<void>;
  setEvents: (events: Event[]) => void;

  addEvent: (event: Event) => Promise<Event>;
  updateEvent: (event: Event) => Promise<Event>;
  removeEvent: (id: string) => Promise<void>;

  markReminderAsSent: (id: string) => Promise<void>;
  reset: () => void;
  clearError: () => void;
}

function getErrorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : "Não foi possível concluir a operação.";
}

export const useEventStore =
  create<EventStore>((set, get) => ({
    events: [],
    loading: false,
    loaded: false,
    error: "",

    fetchEvents: async () => {
      if (get().loading) {
        return;
      }

      set({ loading: true, error: "" });

      try {
        const events =
          await eventApiService.list();

        set({
          events,
          loading: false,
          loaded: true,
        });
      } catch (error) {
        set({
          events: [],
          error: getErrorMessage(error),
          loading: false,
          loaded: true,
        });
      }
    },

    /**
     * Nome mantido para não quebrar o App e componentes existentes.
     */
    hydrateEvents: async () => {
      await get().fetchEvents();
    },

    setEvents: (events) => {
      set({
        events,
        loaded: true,
      });
    },

    addEvent: async (event) => {
      set({ loading: true, error: "" });

      try {
        const created =
          await eventApiService.create(event);

        set((state) => ({
          events: [
            ...state.events,
            created,
          ],
          loading: false,
          loaded: true,
        }));

        return created;
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
        });

        throw error;
      }
    },

    updateEvent: async (event) => {
      set({ loading: true, error: "" });

      try {
        const updated =
          await eventApiService.update(event);

        set((state) => ({
          events:
            state.events.map((current) =>
              current.id === updated.id
                ? updated
                : current
            ),
          loading: false,
        }));

        return updated;
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
        });

        throw error;
      }
    },

    removeEvent: async (id) => {
      set({ loading: true, error: "" });

      try {
        await eventApiService.remove(id);

        set((state) => ({
          events:
            state.events.filter(
              (event) => event.id !== id
            ),
          loading: false,
        }));
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
        });

        throw error;
      }
    },

    /**
     * O schema atual não possui reminder_sent. Mantemos essa flag em memória
     * para preservar o watcher existente sem simular persistência inexistente.
     */
    markReminderAsSent: async (id) => {
      set((state) => ({
        events:
          state.events.map((event) =>
            event.id === id
              ? {
                  ...event,
                  reminderSent: true,
                }
              : event
          ),
      }));
    },

    reset: () => set({
      events: [],
      loading: false,
      loaded: false,
      error: "",
    }),

    clearError: () => set({ error: "" }),
  }));
