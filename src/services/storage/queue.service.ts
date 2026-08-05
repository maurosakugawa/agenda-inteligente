// src/services/storage/queue.service.ts
/**
 * Fila de sincronização offline
 *
 * @author Mauro Sakugawa
 * @created 2026-05-26
 * @license MIT
 * @version 1.0.0
 */

import { db } from "./indexedDb.service";

export type SyncAction =
  | "create"
  | "update"
  | "delete";

export interface SyncQueueItem {
  id: string;

  action: SyncAction;

  payload: unknown;

  createdAt: string;
}

/**
 * Adiciona item na fila
 */
export async function addToQueue(
  item: SyncQueueItem
) {
  await db.syncQueue.add(item);
}

/**
 * Obtém fila completa
 */
export async function getQueue() {
  return await db.syncQueue.toArray();
}

/**
 * Remove item sincronizado
 */
export async function removeFromQueue(
  id: string
) {
  await db.syncQueue.delete(id);
}