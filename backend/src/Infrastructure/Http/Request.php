<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

use JsonException;

final class Request
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $routeParams
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $headers = [],
        private string $body = '',
        private array $routeParams = []
    ) {
    }

    public static function fromGlobals(): self
    {
        $body = file_get_contents(
            'php://input'
        );

        return self::create(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            self::headersFromServer(
                $_SERVER
            ),
            is_string($body)
                ? $body
                : ''
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function create(
        string $method,
        string $uri,
        array $headers = [],
        string $body = ''
    ): self {
        $normalizedMethod = strtoupper(
            trim($method)
        );

        if ($normalizedMethod === '') {
            $normalizedMethod = 'GET';
        }

        $parsedPath = parse_url(
            $uri,
            PHP_URL_PATH
        );

        $path = is_string($parsedPath)
            ? $parsedPath
            : '/';

        return new self(
            $normalizedMethod,
            self::normalizePath($path),
            self::normalizeHeaders($headers),
            $body
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(
        string $name
    ): ?string {
        $normalizedName =
            self::normalizeHeaderName(
                $name
            );

        if ($normalizedName === '') {
            return null;
        }

        return $this->headers[
            $normalizedName
        ] ?? null;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidJsonBodyException
     */
    public function json(): array
    {
        $body = trim(
            $this->body
        );

        if ($body === '') {
            throw new InvalidJsonBodyException(
                'O corpo da requisição deve conter um objeto JSON válido.'
            );
        }

        try {
            $decoded = json_decode(
                $body,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new InvalidJsonBodyException(
                'O corpo da requisição deve conter um objeto JSON válido.',
                0,
                $exception
            );
        }

        /*
         * A API trabalha com documentos JSON cuja raiz é um objeto.
         *
         * json_decode(..., true) converte tanto:
         *
         *     {}
         *
         * quanto:
         *
         *     []
         *
         * para arrays PHP. Por isso também verificamos o primeiro
         * caractere do documento original.
         */
        if (
            !is_array($decoded)
            || !str_starts_with(
                $body,
                '{'
            )
        ) {
            throw new InvalidJsonBodyException(
                'O corpo da requisição deve conter um objeto JSON válido.'
            );
        }

        return $decoded;
    }

    /**
     * @param array<string, string> $routeParams
     */
    public function withRouteParams(
        array $routeParams
    ): self {
        $request = clone $this;
        $request->routeParams = $routeParams;

        return $request;
    }

    /**
     * @return array<string, string>
     */
    public function routeParams(): array
    {
        return $this->routeParams;
    }

    public function routeParam(
        string $name
    ): ?string {
        return $this->routeParams[$name]
            ?? null;
    }

    private static function normalizePath(
        string $path
    ): string {
        $path = trim($path);

        if ($path === '') {
            return '/';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        $path = preg_replace(
            '#/+#',
            '/',
            $path
        ) ?? '/';

        $path = rtrim(
            $path,
            '/'
        );

        return $path === ''
            ? '/'
            : $path;
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array<string, string>
     */
    private static function normalizeHeaders(
        array $headers
    ): array {
        $normalized = [];

        foreach (
            $headers as $name => $value
        ) {
            $normalizedName =
                self::normalizeHeaderName(
                    $name
                );

            if ($normalizedName === '') {
                continue;
            }

            $normalized[
                $normalizedName
            ] = trim($value);
        }

        return $normalized;
    }

    private static function normalizeHeaderName(
        string $name
    ): string {
        return strtolower(
            trim($name)
        );
    }

    /**
     * @param array<string, mixed> $server
     *
     * @return array<string, string>
     */
    private static function headersFromServer(
        array $server
    ): array {
        $headers = [];

        foreach (
            $server as $name => $value
        ) {
            if (!is_string($value)) {
                continue;
            }

            if (
                str_starts_with(
                    $name,
                    'HTTP_'
                )
            ) {
                $headerName =
                    substr(
                        $name,
                        5
                    );

                if ($headerName === '') {
                    continue;
                }

                $headers[
                    str_replace(
                        '_',
                        '-',
                        $headerName
                    )
                ] = $value;

                continue;
            }

            if (
                $name === 'CONTENT_TYPE'
                || $name === 'CONTENT_LENGTH'
            ) {
                $headers[
                    str_replace(
                        '_',
                        '-',
                        $name
                    )
                ] = $value;
            }
        }

        return $headers;
    }
}
