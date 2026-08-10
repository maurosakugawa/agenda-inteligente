import { apiFetch } from "../../../services/http/apiClient";

import type {
  Contact,
  ContactInput,
} from "../types/contact.types";


interface ContactApiError {
  error?: unknown;
}

function getErrorMessage(
  payload: ContactApiError | null
): string {
  if (
    typeof payload?.error === "string" &&
    payload.error.trim()
  ) {
    return payload.error;
  }

  if (
    payload?.error &&
    typeof payload.error === "object" &&
    "message" in payload.error
  ) {
    const message =
      (payload.error as { message?: unknown })
        .message;

    if (
      typeof message === "string" &&
      message.trim()
    ) {
      return message;
    }
  }

  return "Erro na requisição";
}

async function request<T>(
  path: string,
  options: RequestInit = {}
): Promise<T> {
  const response =
    await apiFetch(
      path,
      options
    );

  if (response.status === 401) {
    throw new Error(
      "Não autenticado"
    );
  }

  if (!response.ok) {
    const payload =
      await response
        .json()
        .catch(() => null) as
          | ContactApiError
          | null;

    throw new Error(
      getErrorMessage(
        payload
      )
    );
  }

  return response.json() as Promise<T>;
}

export const contactService = {
  list: (): Promise<Contact[]> =>
    request<Contact[]>(
      "/api/contacts"
    ),

  create: (
    data: ContactInput
  ): Promise<Contact> =>
    request<Contact>(
      "/api/contacts",
      {
        method: "POST",
        headers: {
          "Content-Type":
            "application/json",
        },
        body: JSON.stringify(
          data
        ),
      }
    ),

  update: (
    id: number,
    data: ContactInput
  ): Promise<Contact> =>
    request<Contact>(
      `/api/contacts/${id}`,
      {
        method: "PUT",
        headers: {
          "Content-Type":
            "application/json",
        },
        body: JSON.stringify(
          data
        ),
      }
    ),

  delete: (
    id: number
  ): Promise<{ message: string }> =>
    request<{ message: string }>(
      `/api/contacts/${id}`,
      {
        method: "DELETE",
      }
    ),
};
