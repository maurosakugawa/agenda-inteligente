//src/services/storage/sync.service.ts
/**
 * Serviço de sincronização
 *
 * @author Mauro Sakugawa
 * @created 2026-05-26
 * @license MIT
 * @version 1.0.0
 */
import {
  getQueue,
  removeFromQueue,
} from "./queue.service";

/**
 * Simula envio ao backend
 */
async function fakeApiRequest(
  data: unknown
) {
  console.log(
    "Enviando para API:",
    data
  );

  return new Promise((resolve) => {
    setTimeout(resolve, 500);
  });
}

/**
 * Processa fila
 */
export async function processSyncQueue() {
  const queue = await getQueue();

  for (const item of queue) {
    try {
      /**
       * Futuro:
       * fetch('/api/events')
       */
      await fakeApiRequest(
        item.payload
      );

      console.log(
        "SYNC OK:",
        item.action
      );

      await removeFromQueue(
        item.id
      );

    } catch (error) {
      console.error(
        "SYNC ERROR",
        error
      );
    }
  }
}