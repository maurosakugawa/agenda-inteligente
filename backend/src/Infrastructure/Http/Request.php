<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

final class Request
{
    private function __construct(
        private string $method,
        private string $path
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
