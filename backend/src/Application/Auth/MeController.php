<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Http\JsonResponse;

final class MeController
{
    public function __construct(
        private CurrentUserResolver $currentUser
    ) {
    }

    public function handle(): JsonResponse
    {
        $user =
            $this->currentUser->resolve();

        if ($user === null) {
            return new JsonResponse(
                [
                    'error' =>
                        'Autenticação necessária',
                ],
                401
            );
        }

        return new JsonResponse(
            [
                'id' =>
                    $user['id'],
                'username' =>
                    $user['username'],
            ],
            200
        );
    }
}
