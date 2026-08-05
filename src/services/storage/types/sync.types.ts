// src/services/storage/types/sync.types.ts
/**
 * @author Mauro Sakugawa
 * @created 2026-05-26
 * @license MIT
 * @version 1.0.0
 */
export interface SyncQueueItem {
  id: string;

  action:
    | "create"
    | "update"
    | "delete";

  payload: unknown;

  createdAt: string;
}