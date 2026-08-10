import { apiUrl } from "../../config/api";

interface CsrfResponse {
  csrf_token?: unknown;
  error?: unknown;
  message?: unknown;
}

let cachedCsrfToken: string | null = null;

let pendingCsrfRequest:
  Promise<string> | null = null;

/**
 * Identifica a geração lógica do token CSRF.
 *
 * Quando a sessão muda — por exemplo, após login
 * ou logout — incrementamos esta geração para que
 * uma requisição antiga ainda em andamento não
 * consiga repopular o cache com um token obsoleto.
 */
let csrfGeneration = 0;

function getResponseError(
  body: unknown,
  fallback: string
): string {
  if (
    body === null ||
    typeof body !== "object"
  ) {
    return fallback;
  }

  const data =
    body as Record<string, unknown>;

  if (
    typeof data.error === "string" &&
    data.error.trim()
  ) {
    return data.error;
  }

  if (
    typeof data.message === "string" &&
    data.message.trim()
  ) {
    return data.message;
  }

  return fallback;
}

async function fetchCsrfTokenFromServer():
  Promise<string> {
  const response = await fetch(
    apiUrl("/auth/csrf"),
    {
      method: "GET",
      credentials: "include",

      headers: {
        Accept: "application/json",
      },

      /**
       * O token pertence ao estado atual da sessão.
       * Não queremos que caches HTTP intermediários
       * sejam usados como fonte desse valor.
       */
      cache: "no-store",
    }
  );

  const body =
    await response
      .json()
      .catch(() => null) as
        | CsrfResponse
        | null;

  if (!response.ok) {
    throw new Error(
      getResponseError(
        body,
        "Não foi possível obter o token CSRF."
      )
    );
  }

  const token =
    body?.csrf_token;

  if (
    typeof token !== "string" ||
    token.trim() === ""
  ) {
    throw new Error(
      "O servidor retornou um token CSRF inválido."
    );
  }

  return token.trim();
}

function startCsrfRequest(
  generation: number
): Promise<string> {
  const request =
    fetchCsrfTokenFromServer()
      .then((token) => {
        /**
         * A sessão pode ter sido renovada enquanto
         * esta requisição estava em andamento.
         *
         * Nesse caso o resultado ainda é entregue
         * ao chamador, mas jamais entra no cache da
         * geração nova.
         */
        if (
          generation === csrfGeneration
        ) {
          cachedCsrfToken = token;
        }

        return token;
      })
      .finally(() => {
        /**
         * Uma requisição mais nova pode ter sido
         * iniciada depois de uma rotação de sessão.
         * Não devemos apagá-la daqui.
         */
        if (
          pendingCsrfRequest === request
        ) {
          pendingCsrfRequest = null;
        }
      });

  pendingCsrfRequest = request;

  return request;
}

/**
 * Retorna um token CSRF válido para a geração
 * atual da sessão.
 *
 * Requisições concorrentes compartilham a mesma
 * chamada GET /auth/csrf enquanto o token ainda
 * não estiver disponível.
 */
export async function getCsrfToken():
  Promise<string> {
  if (cachedCsrfToken !== null) {
    return cachedCsrfToken;
  }

  const requestedGeneration =
    csrfGeneration;

  const token =
    await (
      pendingCsrfRequest ??
      startCsrfRequest(
        requestedGeneration
      )
    );

  /**
   * A sessão mudou enquanto aguardávamos.
   * O token recebido pertence à geração anterior,
   * então buscamos o token da sessão atual.
   */
  if (
    requestedGeneration !==
    csrfGeneration
  ) {
    return getCsrfToken();
  }

  return token;
}

/**
 * Remove qualquer token associado à sessão
 * anterior.
 *
 * Deve ser chamado quando houver mudança
 * deliberada de identidade da sessão.
 */
export function clearCsrfToken(): void {
  csrfGeneration += 1;
  cachedCsrfToken = null;

  /**
   * Não podemos cancelar necessariamente um fetch
   * já iniciado, mas ele deixa de pertencer à nova
   * geração e não poderá repopular o cache.
   */
  pendingCsrfRequest = null;
}

/**
 * Descarta o token atual e obtém imediatamente
 * outro token para a sessão corrente.
 *
 * Será usado, por exemplo, após login bem-sucedido,
 * quando o backend regenera a sessão.
 */
export async function refreshCsrfToken():
  Promise<string> {
  clearCsrfToken();

  return getCsrfToken();
}
