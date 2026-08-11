import {
  afterEach,
  describe,
  expect,
  it,
  vi,
} from "vitest";

import {
  clearCsrfToken,
  getCsrfToken,
} from "./csrf";

import {
  apiFetch,
} from "./apiClient";

vi.mock(
  "./csrf",
  () => ({
    getCsrfToken: vi.fn(),
    clearCsrfToken: vi.fn(),
  })
);

const mockedGetCsrfToken =
  vi.mocked(
    getCsrfToken
  );

const mockedClearCsrfToken =
  vi.mocked(
    clearCsrfToken
  );

describe(
  "apiFetch",
  () => {
    afterEach(() => {
      vi.restoreAllMocks();
      vi.unstubAllGlobals();

      mockedGetCsrfToken
        .mockReset();

      mockedClearCsrfToken
        .mockReset();
    });

    it(
      "renova token csrf e repete uma mutação após csrf_invalid",
      async () => {
        mockedGetCsrfToken
          .mockResolvedValueOnce(
            "token-antigo"
          )
          .mockResolvedValueOnce(
            "token-novo"
          );

        const fetchMock =
          vi.fn()
            .mockResolvedValueOnce(
              new Response(
                JSON.stringify({
                  success: false,
                  error: {
                    code: "csrf_invalid",
                    message:
                      "Token CSRF ausente ou inválido.",
                  },
                }),
                {
                  status: 403,
                  headers: {
                    "Content-Type":
                      "application/json",
                  },
                }
              )
            )
            .mockResolvedValueOnce(
              new Response(
                JSON.stringify({
                  success: true,
                  data: {
                    saved: true,
                  },
                }),
                {
                  status: 200,
                  headers: {
                    "Content-Type":
                      "application/json",
                  },
                }
              )
            );

        vi.stubGlobal(
          "fetch",
          fetchMock
        );

        const response =
          await apiFetch(
            "/api/test",
            {
              method: "POST",
              headers: {
                "Content-Type":
                  "application/json",
              },
              body: JSON.stringify({
                title: "Teste",
              }),
            }
          );

        expect(
          response.status
        ).toBe(
          200
        );

        expect(
          fetchMock
        ).toHaveBeenCalledTimes(
          2
        );

        expect(
          mockedClearCsrfToken
        ).toHaveBeenCalledTimes(
          1
        );

        expect(
          mockedGetCsrfToken
        ).toHaveBeenCalledTimes(
          2
        );

        const firstRequest =
          fetchMock.mock.calls[0];

        const secondRequest =
          fetchMock.mock.calls[1];

        const firstOptions =
          firstRequest?.[1] as
            | RequestInit
            | undefined;

        const secondOptions =
          secondRequest?.[1] as
            | RequestInit
            | undefined;

        const firstHeaders =
          new Headers(
            firstOptions?.headers
          );

        const secondHeaders =
          new Headers(
            secondOptions?.headers
          );

        expect(
          firstHeaders.get(
            "X-CSRF-Token"
          )
        ).toBe(
          "token-antigo"
        );

        expect(
          secondHeaders.get(
            "X-CSRF-Token"
          )
        ).toBe(
          "token-novo"
        );
      }
    );

    it(
      "não repete requisição para erro 403 que não seja csrf_invalid",
      async () => {
        mockedGetCsrfToken
          .mockResolvedValue(
            "token-atual"
          );

        const fetchMock =
          vi.fn()
            .mockResolvedValue(
              new Response(
                JSON.stringify({
                  success: false,
                  error: {
                    code: "forbidden",
                    message:
                      "Operação não permitida.",
                  },
                }),
                {
                  status: 403,
                  headers: {
                    "Content-Type":
                      "application/json",
                  },
                }
              )
            );

        vi.stubGlobal(
          "fetch",
          fetchMock
        );

        const response =
          await apiFetch(
            "/api/test",
            {
              method: "POST",
            }
          );

        expect(
          response.status
        ).toBe(
          403
        );

        expect(
          fetchMock
        ).toHaveBeenCalledTimes(
          1
        );

        expect(
          mockedGetCsrfToken
        ).toHaveBeenCalledTimes(
          1
        );

        expect(
          mockedClearCsrfToken
        ).not.toHaveBeenCalled();
      }
    );

    it(
      "repete no máximo uma vez quando csrf_invalid persiste",
      async () => {
        mockedGetCsrfToken
          .mockResolvedValueOnce(
            "token-antigo"
          )
          .mockResolvedValueOnce(
            "token-novo"
          );

        const csrfInvalidResponse =
          () =>
            new Response(
              JSON.stringify({
                success: false,
                error: {
                  code: "csrf_invalid",
                  message:
                    "Token CSRF ausente ou inválido.",
                },
              }),
              {
                status: 403,
                headers: {
                  "Content-Type":
                    "application/json",
                },
              }
            );

        const fetchMock =
          vi.fn()
            .mockResolvedValueOnce(
              csrfInvalidResponse()
            )
            .mockResolvedValueOnce(
              csrfInvalidResponse()
            );

        vi.stubGlobal(
          "fetch",
          fetchMock
        );

        const response =
          await apiFetch(
            "/api/test",
            {
              method: "DELETE",
            }
          );

        expect(
          response.status
        ).toBe(
          403
        );

        expect(
          fetchMock
        ).toHaveBeenCalledTimes(
          2
        );

        expect(
          mockedClearCsrfToken
        ).toHaveBeenCalledTimes(
          1
        );

        expect(
          mockedGetCsrfToken
        ).toHaveBeenCalledTimes(
          2
        );
      }
    );
  }
);