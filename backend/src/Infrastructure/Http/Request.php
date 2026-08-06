<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

final class Request
{
    /**
     * @param array<string, string> $routeParams
     */
    private function __construct(
        private string $method,
        private string $path,
        private array $routeParams = []
    ) {
    }

    public static function fromGlobals(): self
    {
        return self::create(
            (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            (string) ($_SERVER['REQUEST_URI'] ?? '/')
        );
    }

    public static function create(
        string $method,
        string $uri
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
            self::normalizePath($path)
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
}
