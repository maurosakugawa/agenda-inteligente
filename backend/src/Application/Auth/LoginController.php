<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

use AgendaInteligente\Infrastructure\Http\JsonResponse;
use AgendaInteligente\Infrastructure\Http\Request;
use Closure;

final class LoginController
{
    private Closure $clock;

    /**
     * @param null|Closure(): int $clock
     */
    public function __construct(
        private CredentialVerifier $verifier,
        private CredentialValidator $credentials,
        private LoginRateLimiter $rateLimiter,
        private AuthenticationSession $session,
        ?Closure $clock = null
    ) {
        $this->clock =
            $clock
            ?? static fn (): int => time();
    }

    public function handle(
        Request $request
    ): JsonResponse {
        $input =
            $request->json();

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

        /*
         * A validação estrutural precisa ocorrer antes do rate
         * limiting para que entradas inválidas não consumam
         * tentativas.
         *
         * CredentialVerifier mantém sua própria validação como
         * defesa interna.
         */
        try {
            $this->credentials
                ->validateForLogin(
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

        $remoteAddress =
            $request->remoteAddress();

        if (
            $remoteAddress === null
            || $remoteAddress === ''
        ) {
            return JsonResponse::error(
                'service_unavailable',
                'Serviço temporariamente indisponível.',
                503
            );
        }

        $blockedUntil =
            $this->rateLimiter
                ->blockedUntil(
                    $remoteAddress,
                    $username
                );

        if ($blockedUntil !== null) {
            return $this->rateLimitedResponse(
                $blockedUntil
            );
        }

        /*
         * A operação atômica pode descobrir um bloqueio que
         * apareceu entre a consulta acima e este registro.
         */
        $blockedUntil =
            $this->rateLimiter
                ->recordIpAttempt(
                    $remoteAddress
                );

        if ($blockedUntil !== null) {
            return $this->rateLimitedResponse(
                $blockedUntil
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
            /*
             * Esta situação não deve ocorrer porque a mesma
             * política estrutural já foi aplicada acima.
             *
             * Mantemos a tradução defensiva do contrato atual
             * do CredentialVerifier.
             */
            return JsonResponse::error(
                'invalid_credentials_input',
                $exception->getMessage(),
                400
            );
        }

        if ($identity === null) {
            $blockedUntil =
                $this->rateLimiter
                    ->recordCredentialFailure(
                        $remoteAddress,
                        $username
                    );

            if ($blockedUntil !== null) {
                return $this->rateLimitedResponse(
                    $blockedUntil
                );
            }

            return JsonResponse::error(
                'invalid_credentials',
                'Usuário ou senha inválidos.',
                401
            );
        }

        $this->rateLimiter
            ->clearCredentialFailures(
                $remoteAddress,
                $username
            );

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

    private function rateLimitedResponse(
        int $blockedUntil
    ): JsonResponse {
        $retryAfter =
            max(
                1,
                $blockedUntil
                - ($this->clock)()
            );

        return JsonResponse::error(
            'login_rate_limited',
            'Muitas tentativas de login. Tente novamente mais tarde.',
            429,
            [],
            [
                'Retry-After' =>
                    (string) $retryAfter,
            ]
        );
    }
}
