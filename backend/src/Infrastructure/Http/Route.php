<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

use Closure;
use InvalidArgumentException;

final class Route
{
    private string $method;

    private string $pathPattern;

    private string $pathRegex;

    /** @var list<string> */
    private array $parameterNames = [];

    /** @var Closure(Request): JsonResponse */
    private Closure $handler;

    /** @var list<MiddlewareInterface> */
    private array $middlewares;

    /**
     * @param callable(Request): JsonResponse $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function __construct(
        string $method,
        string $pathPattern,
        callable $handler,
        array $middlewares = []
    ) {
        $method = strtoupper(
            trim($method)
        );

        if ($method === '') {
            throw new InvalidArgumentException(
                'O método HTTP da rota não pode ficar vazio.'
            );
        }

        foreach ($middlewares as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(
                    'Todos os middlewares da rota devem implementar MiddlewareInterface.'
                );
            }
        }

        $this->method = $method;
        $this->pathPattern = Request::create(
            'GET',
            $pathPattern
        )->path();
        $this->handler = Closure::fromCallable(
            $handler
        );
        $this->middlewares = array_values(
            $middlewares
        );
        $this->pathRegex = $this->compilePathPattern(
            $this->pathPattern
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function pathPattern(): string
    {
        return $this->pathPattern;
    }

    public function structuralPattern(): string
    {
        return preg_replace(
            '/\\{[A-Za-z_][A-Za-z0-9_]*\\}/',
            '{}',
            $this->pathPattern
        ) ?? $this->pathPattern;
    }

    /**
     * @return array<string, string>|null
     */
    public function matchPath(
        string $path
    ): ?array {
        $matches = [];
        $matched = preg_match(
            $this->pathRegex,
            $path,
            $matches
        );

        if ($matched !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($this->parameterNames as $name) {
            $parameters[$name] = rawurldecode(
                (string) ($matches[$name] ?? '')
            );
        }

        return $parameters;
    }

    public function dispatch(
        Request $request
    ): JsonResponse {
        $pipeline = new MiddlewarePipeline(
            $this->middlewares,
            $this->handler
        );

        return $pipeline->handle(
            $request
        );
    }

    private function compilePathPattern(
        string $pathPattern
    ): string {
        if ($pathPattern === '/') {
            return '#^/$#D';
        }

        $segments = explode(
            '/',
            trim($pathPattern, '/')
        );
        $compiledSegments = [];
        $knownParameters = [];

        foreach ($segments as $segment) {
            $placeholder = [];

            if (
                preg_match(
                    '/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/D',
                    $segment,
                    $placeholder
                ) === 1
            ) {
                $name = $placeholder[1];

                if (isset($knownParameters[$name])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'O parâmetro de rota "%s" foi declarado mais de uma vez.',
                            $name
                        )
                    );
                }

                $knownParameters[$name] = true;
                $this->parameterNames[] = $name;
                $compiledSegments[] = sprintf(
                    '(?P<%s>[^/]+)',
                    $name
                );

                continue;
            }

            if (
                str_contains($segment, '{')
                || str_contains($segment, '}')
            ) {
                throw new InvalidArgumentException(
                    'Parâmetros de rota devem ocupar um segmento completo.'
                );
            }

            $compiledSegments[] = preg_quote(
                $segment,
                '#'
            );
        }

        return '#^/'
            . implode('/', $compiledSegments)
            . '$#D';
    }
}
