// src/services/storage/indexedDb.service.ts

/**
 * IndexedDB service
 *
 * @author Mauro Sakugawa
 * @created 2026-05-26
 * @license MIT
 * @version 1.0.0
 */

import Dexie, { type Table, } from "dexie";

import type { Event, } from "../../modules/events/types/event.types";
import type { SyncQueueItem } from "./queue.service";

export class SmartPlannerDB extends Dexie {

  /**
   * Tabela de eventos
   */
  events!: Table<Event>;

  /**
   * Fila de sincronização
   */
  syncQueue!: Table<SyncQueueItem>;

  constructor() {
    super("SmartPlannerDB");

    this.version(1).stores({
      events:
        "id, date, category",

      syncQueue:
        "id, action, createdAt",
    });
  }
}

export const db =
  new SmartPlannerDB();