import { create } from "zustand";

import { apiFetch } from "../services/http/apiClient";
import {
  clearCsrfToken,
  refreshCsrfToken,
} from "../services/http/csrf";


interface User {
  id: number;
  username: string;
}

interface ApiError {
  code?: unknown;
  message?: unknown;
}

interface ApiResponse {
  success?: unknown;
  error?: ApiError | string;
  message?: unknown;
}

interface LoginResponse extends ApiResponse {
  data?: {
    message?: unknown;
    user?: User;
    csrf_token?: unknown;
  };
}

interface RegisterResponse extends ApiResponse {
  data?: {
    message?: unknown;
    userId?: unknown;
  };
}

interface AuthStore {
  user: User | null;
  loading: boolean;
  initialized: boolean;
  error: string;

  checkAuth: () => Promise<void>;
  login: (
    username: string,
    password: string
  ) => Promise<void>;
  register: (
    username: string,
    password: string
  ) => Promise<void>;
  logout: () => Promise<void>;
  clearError: () => void;
}

function getErrorMessage(error: unknown): string {
  return error instanceof Error
    ? error.message
    : "Não foi possível concluir a operação.";
}

function getApiErrorMessage(
  payload: ApiResponse | null,
  fallback: string
): string {
  if (payload === null) {
    return fallback;
  }

  if (
    typeof payload.error === "string" &&
    payload.error.trim()
  ) {
    return payload.error;
  }

  if (
    payload.error &&
    typeof payload.error === "object" &&
    typeof payload.error.message === "string" &&
    payload.error.message.trim()
  ) {
    return payload.error.message;
  }

  if (
    typeof payload.message === "string" &&
    payload.message.trim()
  ) {
    return payload.message;
  }

  return fallback;
}

export const useAuthStore =
  create<AuthStore>((set) => ({
    user: null,
    loading: false,
    initialized: false,
    error: "",

    checkAuth: async () => {
      set({ loading: true });

      try {
        const response =
          await apiFetch("/auth/me");

        if (response.ok) {
          /**
           * /auth/me preserva intencionalmente
           * o contrato sem envelope:
           *
           * {
           *   "id": 1,
           *   "username": "usuario"
           * }
           */
          const user =
            await response.json() as User;

          set({ user });
        } else {
          if (response.status === 401) {
            clearCsrfToken();
          }

          set({ user: null });
        }
      } catch {
        set({ user: null });
      } finally {
        set({
          loading: false,
          initialized: true,
        });
      }
    },

    login: async (username, password) => {
      set({
        loading: true,
        error: "",
      });

      try {
        const response =
          await apiFetch(
            "/auth/login",
            {
              method: "POST",
              headers: {
                "Content-Type":
                  "application/json",
              },
              body: JSON.stringify({
                username,
                password,
              }),
            }
          );

        const payload =
          await response
            .json()
            .catch(() => null) as
              | LoginResponse
              | null;

        const user =
          payload?.data?.user;

        if (!response.ok || !user) {
          throw new Error(
            getApiErrorMessage(
              payload,
              "Login falhou."
            )
          );
        }

        /**
         * O backend regenera a sessão durante
         * o login. O token usado para autenticar
         * pertence à sessão anterior.
         */
        try {
          await refreshCsrfToken();
        } catch {
          /**
           * O login já ocorreu no servidor.
           * Mantemos o usuário autenticado e
           * deixamos a próxima mutação obter
           * novamente um token.
           */
          clearCsrfToken();
        }

        set({
          user,
          loading: false,
          initialized: true,
        });
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
          initialized: true,
        });

        throw error;
      }
    },

    register: async (username, password) => {
      set({
        loading: true,
        error: "",
      });

      try {
        const response =
          await apiFetch(
            "/auth/register",
            {
              method: "POST",
              headers: {
                "Content-Type":
                  "application/json",
              },
              body: JSON.stringify({
                username,
                password,
              }),
            }
          );

        const payload =
          await response
            .json()
            .catch(() => null) as
              | RegisterResponse
              | null;

        if (!response.ok) {
          throw new Error(
            getApiErrorMessage(
              payload,
              "Registro falhou."
            )
          );
        }

        set({
          loading: false,
          initialized: true,
        });
      } catch (error) {
        set({
          error: getErrorMessage(error),
          loading: false,
          initialized: true,
        });

        throw error;
      }
    },

    logout: async () => {
      try {
        await apiFetch(
          "/auth/logout",
          {
            method: "POST",
          }
        );
      } finally {
        clearCsrfToken();

        set({
          user: null,
          initialized: true,
        });
      }
    },

    clearError: () => set({ error: "" }),
  }));
