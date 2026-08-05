// src/services/storage/localStorage.service.ts
/**
 * Serviço de persistência local
 *
 * @author Mauro Sakugawa
 * @created 2026-05-25
 * @license MIT license
 * @version 1.0.0
 */

export function saveToStorage<T>(
  key: string,
  data: T
) {
  localStorage.setItem(
    key,
    JSON.stringify(data)
  );
}

export function loadFromStorage<T>(
  key: string,
  fallback: T
): T {
  const stored =
    localStorage.getItem(key);

  if (!stored) {
    return fallback;
  }

  try {
    return JSON.parse(stored);
  } catch {
    return fallback;
  }
}