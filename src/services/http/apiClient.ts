import { apiUrl } from "../../config/api";
import { getCsrfToken } from "./csrf";

const CSRF_PROTECTED_METHODS =
  new Set([
    "POST",
    "PUT",
    "PATCH",
    "DELETE",
  ]);

function normalizeMethod(
  method?: string
): string {
  const normalized =
    method
      ?.trim()
      .toUpperCase();

  return normalized || "GET";
}

/**
 * Informa se o método HTTP exige proteção CSRF
 * pela política atual da aplicação.
 *
 * A função é exportada também para permitir testes
 * unitários da política sem depender de fetch().
 */
export function isCsrfProtectedMethod(
  method?: string
): boolean {
  return CSRF_PROTECTED_METHODS.has(
    normalizeMethod(method)
  );
}

/**
 * Cliente HTTP para endpoints internos da
 * Agenda Inteligente.
 *
 * Responsabilidades transversais:
 *
 * - resolver a URL pelo apiUrl();
 * - sempre enviar cookies de sessão;
 * - anexar automaticamente X-CSRF-Token
 *   nas requisições mutáveis.
 *
 * Parsing de JSON, tratamento de domínio e
 * mensagens específicas permanecem nos serviços
 * consumidores.
 */
export async function apiFetch(
  path: string,
  options: RequestInit = {}
): Promise<Response> {
  const method =
    normalizeMethod(options.method);

  const headers =
    new Headers(options.headers);

  if (
    isCsrfProtectedMethod(method)
  ) {
    const csrfToken =
      await getCsrfToken();

    /**
     * set(), e não append(), é intencional.
     *
     * O cliente HTTP é a fonte de verdade para
     * esse header. Um chamador não pode deixar
     * acidentalmente dois tokens ou reutilizar
     * manualmente um valor antigo.
     */
    headers.set(
      "X-CSRF-Token",
      csrfToken
    );
  }

  return fetch(
    apiUrl(path),
    {
      ...options,

      /**
       * Estas propriedades vêm depois do spread
       * propositalmente: são invariantes da nossa
       * camada HTTP interna.
       */
      method,
      credentials: "include",
      headers,
    }
  );
}
