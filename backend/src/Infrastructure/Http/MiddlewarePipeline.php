<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

use Closure;
use InvalidArgumentException;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middlewares;

    /** @var Closure(Request): JsonResponse */
    private Closure $destination;

    private int $position = 0;

    /**
     * @param list<MiddlewareInterface> $middlewares
     * @param callable(Request): JsonResponse $destination
     */
    public function __construct(
        array $middlewares,
        callable $destination
    ) {
        foreach ($middlewares as $middleware) {
            if (!$middleware instanceof MiddlewareInterface) {
                throw new InvalidArgumentException(
                    'Todos os middlewares devem implementar MiddlewareInterface.'
                );
            }
        }

        $this->middlewares = array_values(
            $middlewares
        );
        $this->destination = Closure::fromCallable(
            $destination
        );
    }

    public function handle(
        Request $request
    ): JsonResponse {
        $middleware = $this->middlewares[$this->position]
            ?? null;

        if ($middleware === null) {
            return ($this->destination)(
                $request
            );
        }

        $next = clone $this;
        $next->position++;

        return $middleware->process(
            $request,
            $next
        );
    }
}
