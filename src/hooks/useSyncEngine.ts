// src/hooks/useSyncEngine.ts
/**
 * Engine global de sincronização
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT
 * @version 1.0.0
 */

import { useEffect } from "react";

import {
  processSyncQueue,
} from "../services/storage/sync.service";

export function useSyncEngine() {
  useEffect(() => {
    /**
     * Processa ao iniciar
     */
    processSyncQueue();

    /**
     * Reprocessa a cada 30s
     */
    const interval =
      setInterval(() => {
        processSyncQueue();
      }, 30000);

    return () =>
      clearInterval(interval);

  }, []);
}