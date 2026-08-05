// src/modules/events/store/useEventModal.tsx
/**
 *
 * @author Mauro Sakugawa
 * @created 2026-05-21
 * @license MIT License
 * @version 1.0.0
 */
import { create } from "zustand";

import type { Event } from "../types/event.types";

interface EventModalStore {
  isOpen: boolean;

  editingEvent: Event | null;

  openCreate: () => void;

  openEdit: (event: Event) => void;

  close: () => void;
}

export const useEventModal =
  create<EventModalStore>((set) => ({
    isOpen: false,

    editingEvent: null,

    openCreate: () =>
      set({
        isOpen: true,
        editingEvent: null,
      }),

    openEdit: (event) =>
      set({
        isOpen: true,
        editingEvent: event,
      }),

    close: () =>
      set({
        isOpen: false,
        editingEvent: null,
      }),
  }));