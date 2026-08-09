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
          const user =
            await response.json() as User;

          set({ user });
        } else {
          /**
           * Se a sessão deixou de ser autenticada,
           * qualquer token CSRF associado à sessão
           * anterior não deve continuar no cache.
           */
          if (response.status === 401) {
            clearCsrfToken();
          }

          set({ user: null });
        }
      } catch {
        /**
         * Uma falha de rede não significa
         * necessariamente que a sessão expirou.
         * Por isso não invalidamos o CSRF aqui.
         */
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

        const data =
          await response.json() as {
            error?: string;
            user?: User;
          };

        if (!response.ok || !data.user) {
          throw new Error(
            data.error || "Login falhou."
          );
        }

        /**
         * O backend regenera a sessão após
         * autenticação bem-sucedida.
         *
         * O token usado no POST /auth/login
         * pertence à sessão anterior e deve ser
         * substituído pelo token da nova sessão.
         *
         * Se a renovação imediata falhar por um
         * problema transitório de rede, o login já
         * ocorreu no servidor. Mantemos a sessão
         * autenticada e deixamos o cache vazio para
         * que a próxima mutação tente obter outro
         * token automaticamente.
         */
        try {
          await refreshCsrfToken();
        } catch {
          clearCsrfToken();
        }

        set({
          user: data.user,
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

        const data =
          await response.json() as {
            error?: string;
          };

        if (!response.ok) {
          throw new Error(
            data.error || "Registro falhou."
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
        /**
         * O CSRF precisa continuar disponível até
         * o POST terminar, pois logout também é uma
         * operação mutável protegida.
         */
        await apiFetch(
          "/auth/logout",
          {
            method: "POST",
          }
        );
      } finally {
        /**
         * Depois da tentativa de encerramento da
         * sessão, nenhum token da identidade
         * anterior permanece no frontend.
         */
        clearCsrfToken();

        set({
          user: null,
          initialized: true,
        });
      }
    },

    clearError: () => set({ error: "" }),
  }));
