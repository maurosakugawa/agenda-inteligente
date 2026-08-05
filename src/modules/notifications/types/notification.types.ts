// src/modules/notifications/types/notification.types.ts

/**
 * Tipagens do sistema de notificações
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

export type NotificationType =
  | "success"
  | "error"
  | "warning"
  | "info";

export interface Notification {
  id: string;

  title: string;

  message?: string;

  type: NotificationType;

  duration?: number;
}