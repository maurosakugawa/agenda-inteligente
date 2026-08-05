// src/modules/notifications/hooks/useNotifications.ts

/**
 * Hook utilitário de notificações
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import { useNotificationStore }
  from "../store/useNotificationStore";

export function useNotifications() {
  const addNotification =
    useNotificationStore(
      (state) =>
        state.addNotification
    );

  const notify = (
    type:
    | "success"
    | "error"
    | "warning"
    | "info",

    title: string,
    message?: string
  ) => {
    addNotification({
      type,
      title,
      message,
    });
  };

  return {
    success: (
      title: string,
      message?: string
    ) =>
      notify(
        "success",
        title,
        message
      ),

    error: (
      title: string,
      message?: string
    ) =>
      notify(
        "error",
        title,
        message
      ),

    warning: (
      title: string,
      message?: string
    ) =>
      notify(
        "warning",
        title,
        message
      ),

    info: (
      title: string,
      message?: string
    ) =>
      notify(
        "info",
        title,
        message
      ),
  };
}