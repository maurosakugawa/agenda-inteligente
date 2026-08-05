// src/modules/notifications/store/useNotificationStore.ts

/**
 * Store global das notificações
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import { create } from "zustand";

import type {
  Notification,
} from "../types/notification.types";

interface NotificationStore {
  notifications: Notification[];

  addNotification: (
    notification: Omit<Notification, "id">
  ) => void;

  removeNotification: (
    id: string
  ) => void;
}

export const useNotificationStore =
  create<NotificationStore>((set) => ({
    notifications: [],

    addNotification: (
      notification
    ) => {
      const id = crypto.randomUUID();

      const newNotification = {
        ...notification,
        id,
      };

      set((state) => ({
        notifications: [
          ...state.notifications,
          newNotification,
        ],
      }));

      setTimeout(() => {
        set((state) => ({
          notifications:
            state.notifications.filter(
              (item) =>
                item.id !== id
            ),
        }));
      }, notification.duration ?? 4000);
    },

    removeNotification: (
      id
    ) => {
      set((state) => ({
        notifications:
          state.notifications.filter(
            (item) =>
              item.id !== id
          ),
      }));
    },
  }));