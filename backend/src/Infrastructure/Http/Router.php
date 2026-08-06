<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Http;

use InvalidArgumentException;

final class Router implements RequestHandlerInterface
{
    /** @var list<Route> */
    private array $routes = [];

    /**
     * @param callable(Request): JsonResponse $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function get(
        string $pathPattern,
        callable $handler,
        array $middlewares = []
    ): self {
        return $this->add(
            'GET',
            $pathPattern,
            $handler,
            $middlewares
        );
    }

    /**
     * @param callable(Request): JsonResponse $handler
     * @param list<MiddlewareInterface> $middlewares
     */
    public function add(
        string $method,
        string $pathPattern,
        callable $handler,
        array $middlewares = []
    ): self {
        $route = new Route(
            $method,
            $pathPattern,
            $handler,
            $middlewares
        );

        foreach ($this->routes as $registeredRoute) {
            if (
                $registeredRoute->method() === $route->method()
                && $registeredRoute->structuralPattern()
                    === $route->structuralPattern()
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'A rota %s %s já está registrada.',
                        $route->method(),
                        $route->pathPattern()
                    )
                );
            }
        }

        $this->routes[] = $route;

        return $this;
    }

    public function handle(
        Request $request
    ): JsonResponse {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            try {
                $routeParameters = $route->matchPath(
                    $request->path()
                );
            } catch (
                InvalidRouteParameterException
            ) {
                return JsonResponse::error(
                    'invalid_route_parameter',
                    'Parâmetro de rota inválido.',
                    400
                );
            }

            if ($routeParameters === null) {
                continue;
            }

            if ($route->method() !== $request->method()) {
                $allowedMethods[] = $route->method();

                continue;
            }

            return $route->dispatch(
                $request->withRouteParams(
                    $routeParameters
                )
            );
        }

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(
                array_unique($allowedMethods)
            );
            sort($allowedMethods);

            return JsonResponse::error(
                'method_not_allowed',
                'Método não permitido.',
                405,
                [
                    'allowed_methods' => $allowedMethods,
                ],
                [
                    'Allow' => implode(
                        ', ',
                        $allowedMethods
                    ),
                ]
            );
        }

        return JsonResponse::error(
            'route_not_found',
            'Rota não encontrada.',
            404
        );
    }
}
