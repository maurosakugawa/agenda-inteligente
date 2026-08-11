import { apiUrl } from "../../config/api";
import {
  clearCsrfToken,
  getCsrfToken,
} from "./csrf";

const CSRF_PROTECTED_METHODS =
  new Set([
    "POST",
    "PUT",
    "PATCH",
    "DELETE",
  ]);

interface ApiErrorResponse {
  error?: {
    code?: unknown;
  };
}

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
 * Identifica exclusivamente a resposta usada pelo
 * backend para sinalizar falha de validação CSRF.
 *
 * clone() é intencional para que a resposta original
 * continue disponível ao consumidor caso não haja
 * retry ou caso a segunda tentativa também falhe.
 */
async function isCsrfInvalidResponse(
  response: Response
): Promise<boolean> {
  if (response.status !== 403) {
    return false;
  }

  const body =
    await response
      .clone()
      .json()
      .catch(() => null) as
        | ApiErrorResponse
        | null;

  return (
    body !== null &&
    typeof body === "object" &&
    body.error !== null &&
    typeof body.error === "object" &&
    body.error?.code === "csrf_invalid"
  );
}

/**
 * Executa uma única tentativa HTTP.
 *
 * Para métodos protegidos, o token CSRF fornecido
 * pelo cliente substitui qualquer valor enviado
 * manualmente pelo chamador.
 */
async function performRequest(
  path: string,
  options: RequestInit,
  method: string,
  csrfToken?: string
): Promise<Response> {
  const headers =
    new Headers(
      options.headers
    );

  if (csrfToken !== undefined) {
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

/**
 * Cliente HTTP para endpoints internos da
 * Agenda Inteligente.
 *
 * Responsabilidades transversais:
 *
 * - resolver a URL pelo apiUrl();
 * - sempre enviar cookies de sessão;
 * - anexar automaticamente X-CSRF-Token
 *   nas requisições mutáveis;
 * - recuperar uma única vez de token CSRF obsoleto
 *   quando o backend responder csrf_invalid.
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
    normalizeMethod(
      options.method
    );

  const csrfProtected =
    isCsrfProtectedMethod(
      method
    );

  const csrfToken =
    csrfProtected
      ? await getCsrfToken()
      : undefined;

  const response =
    await performRequest(
      path,
      options,
      method,
      csrfToken
    );

  if (
    !csrfProtected ||
    !(await isCsrfInvalidResponse(
      response
    ))
  ) {
    return response;
  }

  /**
   * O token pode ter sido rotacionado por outra
   * aba ou por uma mudança de sessão.
   *
   * O retry acontece exatamente uma vez. A resposta
   * da segunda tentativa é devolvida diretamente,
   * mesmo se também for csrf_invalid.
   */
  clearCsrfToken();

  const refreshedCsrfToken =
    await getCsrfToken();

  return performRequest(
    path,
    options,
    method,
    refreshedCsrfToken
  );
}
