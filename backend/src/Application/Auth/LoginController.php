<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;

final class LoginController
{
    public function __construct(
        private CredentialVerifier $verifier,
        private AuthenticationSession $session
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
                $this->verifier->verify(
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
        }

        if ($identity === null) {
            return JsonResponse::error(
                'invalid_credentials',
                'Usuário ou senha inválidos.',
                401
            );
        }

        $csrfToken =
            $this->session->establish(
                $identity
            );

        return JsonResponse::success(
            [
                'message' =>
                    'Login realizado',
                'user' => [
                    'id' =>
                        $identity['id'],
                    'username' =>
                        $identity['username'],
                ],
                'csrf_token' =>
                    $csrfToken,
            ],
            200
        );
    }
}
