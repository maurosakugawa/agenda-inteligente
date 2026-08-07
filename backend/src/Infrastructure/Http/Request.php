<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

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
        private array $routeParams = []
    ) {
    }

    public static function fromGlobals(): self
    {
        return self::create(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            self::headersFromServer(
                $_SERVER
            )
        );
    }

    /**
     * @param array<string, string> $headers
     */
    public static function create(
        string $method,
        string $uri,
        array $headers = []
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
            self::normalizeHeaders($headers)
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
