// src/modules/events/services/eventService.ts
/**
 *
 * @author Mauro Sakugawa
 * @created 2026-05-26
 * @license MIT License
 * @version 1.0.0
 */
import { db }
  from "../../../database/db";

import type {
  Event,
} from "../types/event.types";

export async function getEvents() {
  return await db.events.toArray();
}

export async function createEvent(
  event: Event
) {
  return await db.events.add(event);
}

export async function updateEvent(
  event: Event
) {
  return await db.events.put(event);
}

export async function deleteEvent(
  id: string
) {
  return await db.events.delete(id);
}