<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;
use AgendaInteligente\Infrastructure\Persistence\DuplicateUsernameException;

final class RegisterController
{
    public function __construct(
        private UserRegistrar $registrar
    ) {
    }

    public function handle(
        Request $request
    ): JsonResponse {
        $input = $request->json();

        $username =
            $input['username']
            ?? null;

        $password =
            $input['password']
            ?? null;

        if (
            !is_string($username)
            || !is_string($password)
        ) {
            return JsonResponse::error(
                'invalid_credentials_input',
                'Username e senha são obrigatórios.',
                400
            );
        }

        try {
            $identity =
                $this->registrar->register(
                    $username,
                    $password
                );
        } catch (
            InvalidCredentialsInputException $exception
        ) {
            return JsonResponse::error(
                'invalid_credentials_input',
                $exception->getMessage(),
                400
            );
        } catch (
            DuplicateUsernameException
        ) {
            return JsonResponse::error(
                'username_already_exists',
                'Usuário já existe.',
                409
            );
        }

        return JsonResponse::success(
            [
                'message' =>
                    'Usuário criado',
                'userId' =>
                    $identity['id'],
            ],
            201
        );
    }
}
