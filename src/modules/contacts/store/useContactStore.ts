import { create } from "zustand";

import { contactService }
  from "../services/contactService";

import type {
  Contact,
  ContactInput,
} from "../types/contact.types";

interface ContactStore {
  contacts: Contact[];
  loading: boolean;
  loaded: boolean;
  error: string;

  fetchContacts: () => Promise<void>;
  createContact: (
    data: ContactInput
  ) => Promise<void>;
  updateContact: (
    id: number,
    data: ContactInput
  ) => Promise<void>;
  deleteContact: (id: number) => Promise<void>;
  reset: () => void;
  clearError: () => void;
}

function getErrorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : "Não foi possível concluir a operação.";
}

export const useContactStore =
  create<ContactStore>((set, get) => ({
    contacts: [],
    loading: false,
    loaded: false,
    error: "",

    fetchContacts: async () => {
      if (get().loading) {
        return;
      }

      set({ loading: true, error: "" });

      try {
        const contacts =
          await contactService.list();

        set({
          contacts,
          loading: false,
          loaded: true,
        });
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
          loaded: true,
        });
      }
    },

    createContact: async (data) => {
      set({ loading: true, error: "" });

      try {
        const contact =
          await contactService.create(data);

        set({
          contacts: [
            contact,
            ...get().contacts,
          ],
          loading: false,
          loaded: true,
        });
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
        });

        throw error;
      }
    },

    updateContact: async (id, data) => {
      set({ loading: true, error: "" });

      try {
        const contact =
          await contactService.update(id, data);

        set({
          contacts:
            get().contacts.map((current) =>
              current.id === id
                ? contact
                : current
            ),
          loading: false,
        });
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
        });

        throw error;
      }
    },

    deleteContact: async (id) => {
      set({ loading: true, error: "" });

      try {
        await contactService.delete(id);

        set({
          contacts:
            get().contacts.filter(
              (contact) =>
                contact.id !== id
            ),
          loading: false,
        });
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
        });

        throw error;
      }
    },

    reset: () => set({
      contacts: [],
      loading: false,
      loaded: false,
      error: "",
    }),

    clearError: () => set({ error: "" }),
  }));
