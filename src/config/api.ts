/**
 * Endereço-base da API.
 *
 * Por padrão, frontend e backend usam a mesma origem:
 *
 *   /auth/*
 *   /api/*
 *
 * Durante o desenvolvimento, o Vite encaminha essas rotas
 * para o servidor PHP configurado em vite.config.ts.
 *
 * VITE_API_BASE_URL pode ser informado somente quando o
 * frontend e o backend estiverem intencionalmente hospedados
 * em origens diferentes.
 */

const configuredBaseUrl =
  import.meta.env.VITE_API_BASE_URL?.trim();

export const API_BASE_URL =
  configuredBaseUrl
    ? configuredBaseUrl.replace(/\/+$/, '')
    : '';

export function apiUrl(path: string): string {
  const normalizedPath =
    path.startsWith('/')
      ? path
      : `/${path}`;

  return `${API_BASE_URL}${normalizedPath}`;
}
