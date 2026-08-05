// src/modules/events/services/eventDb.service.ts
/**
 * Service IndexedDB dos eventos
 *
 * @author Mauro Sakugawa
 * @created 2026-05-26
 * @license MIT
 * @version 1.0.0
 */

import { db }
  from "../../../database/db";

import type {
  Event,
} from "../types/event.types";

/**
 * Busca todos os eventos
 */
export async function getAllEvents() {
  return await db.events.toArray();
}

/**
 * Salva evento
 */
export async function saveEvent(
  event: Event
) {
  return await db.events.add(event);
}

/**
 * Atualiza evento
 */
export async function updateEvent(
  event: Event
) {
  return await db.events.put(event);
}

/**
 * Remove evento
 */
export async function deleteEvent(
  id: string
) {
  return await db.events.delete(id);
}