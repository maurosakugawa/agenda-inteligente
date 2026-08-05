// src/database/db.ts
/**
 * Banco IndexedDB principal
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import Dexie, {
  type Table,
} from "dexie";

import type {
  Event,
} from "../modules/events/types/event.types";

export class SmartPlannerDB extends Dexie {
  events!: Table<Event>;

  constructor() {
    super("smart-planner-db");

    this.version(1).stores({
      /**
       * Índices
       */
      events:
        "id, title, date, priority, category",
    });
  }
}

export const db =
  new SmartPlannerDB();