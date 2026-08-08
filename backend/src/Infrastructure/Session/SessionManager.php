<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Session;

use Closure;
use RuntimeException;
use Throwable;

final class SessionManager
{
    private Closure $clock;

    /**
     * @param array{
     *     name:string,
     *     secure:bool,
     *     same_site:string,
     *     idle_timeout:int,
     *     absolute_timeout:int
     * } $config
     */
    public function __construct(
        private array $config,
        private ?string $savePath = null,
        ?Closure $clock = null
    ) {
        $this->clock = $clock
            ?? static fn (): int => time();
    }

    public function start(): void
    {
        if ($this->isStarted()) {
            return;
        }

        if (session_status() === PHP_SESSION_DISABLED) {
            throw new RuntimeException(
                'O suporte a sessões PHP está desabilitado.'
            );
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(
                sprintf(
                    'A sessão não pode ser iniciada após envio de saída em %s:%d.',
                    $file,
                    $line
                )
            );
        }

        $this->configure();

        if (!session_start()) {
            throw new RuntimeException(
                'Não foi possível iniciar a sessão.'
            );
        }

        try {
            $this->enforceLifetime();
        } catch (Throwable $exception) {
            $this->close();

            throw $exception;
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }

    public function id(): string
    {
        $this->requireStarted();

        return session_id();
    }

    public function regenerateId(): void
    {
        $this->requireStarted();

        if (!session_regenerate_id(true)) {
            throw new RuntimeException(
                'Não foi possível regenerar o identificador da sessão.'
            );
        }
    }

    public function renewLifetime(): void
    {
        $this->requireStarted();

        $this->initializeSecurity(
            ($this->clock)()
        );
    }

    public function get(
        string $key,
        mixed $default = null
    ): mixed {
        $this->requireStarted();

        return $_SESSION[$key] ?? $default;
    }

    public function set(
        string $key,
        mixed $value
    ): void {
        $this->requireStarted();

        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        $this->requireStarted();

        unset($_SESSION[$key]);
    }

    public function close(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        session_write_close();
    }

    public function destroy(): void
    {
        if (!$this->isStarted()) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies') === '1') {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite']
                        ?? $this->config['same_site'],
                ]
            );
        }

        if (!session_destroy()) {
            throw new RuntimeException(
                'Não foi possível destruir a sessão.'
            );
        }
    }

    private function configure(): void
    {
        $this->setIni(
            'session.use_cookies',
            '1'
        );

        $this->setIni(
            'session.use_only_cookies',
            '1'
        );

        $this->setIni(
            'session.use_strict_mode',
            '1'
        );

        $this->setIni(
            'session.use_trans_sid',
            '0'
        );

        $this->setIni(
            'session.cookie_httponly',
            '1'
        );

        $currentGcMaxLifetime = (int) ini_get(
            'session.gc_maxlifetime'
        );

        $gcMaxLifetime = max(
            $currentGcMaxLifetime,
            $this->config['idle_timeout']
        );

        $this->setIni(
            'session.gc_maxlifetime',
            (string) $gcMaxLifetime
        );

        if (
            $this->savePath !== null
            && $this->savePath !== ''
        ) {
            if (
                !is_dir($this->savePath)
                || !is_writable($this->savePath)
            ) {
                throw new RuntimeException(
                    'O diretório de sessões não existe ou não é gravável.'
                );
            }

            if (
                session_save_path(
                    $this->savePath
                ) === false
            ) {
                throw new RuntimeException(
                    'Não foi possível configurar o diretório de sessões.'
                );
            }
        }

        if (
            session_name(
                $this->config['name']
            ) === false
        ) {
            throw new RuntimeException(
                'Não foi possível configurar o nome da sessão.'
            );
        }

        if (
            !session_set_cookie_params(
                [
                    'lifetime' => 0,
                    'path' => '/',
                    'secure' => $this->config['secure'],
                    'httponly' => true,
                    'samesite' => $this->config['same_site'],
                ]
            )
        ) {
            throw new RuntimeException(
                'Não foi possível configurar o cookie da sessão.'
            );
        }
    }

    private function enforceLifetime(): void
    {
        $now = ($this->clock)();

        $security = $_SESSION['security']
            ?? null;

        if ($security === null) {
            if ($_SESSION !== []) {
                $this->rotateToAnonymousSession(
                    $now
                );

                return;
            }

            $this->initializeSecurity(
                $now
            );

            return;
        }

        if (!$this->hasValidSecurityState($security)) {
            $this->rotateToAnonymousSession(
                $now
            );

            return;
        }

        $lastActivityAt =
            $security['last_activity_at'];

        $absoluteExpiresAt =
            $security['absolute_expires_at'];

        $idleExpired =
            ($now - $lastActivityAt)
            >= $this->config['idle_timeout'];

        $absoluteExpired =
            $now >= $absoluteExpiresAt;

        if (
            $idleExpired
            || $absoluteExpired
        ) {
            $this->rotateToAnonymousSession(
                $now
            );

            return;
        }

        $_SESSION['security']['last_activity_at'] =
            $now;
    }

    private function initializeSecurity(
        int $now
    ): void {
        $_SESSION['security'] = [
            'created_at' => $now,
            'last_activity_at' => $now,
            'absolute_expires_at' =>
                $now
                + $this->config['absolute_timeout'],
        ];
    }

    private function rotateToAnonymousSession(
        int $now
    ): void {
        $_SESSION = [];

        $this->regenerateId();

        $this->initializeSecurity(
            $now
        );
    }

    private function hasValidSecurityState(
        mixed $security
    ): bool {
        if (!is_array($security)) {
            return false;
        }

        foreach (
            [
                'created_at',
                'last_activity_at',
                'absolute_expires_at',
            ] as $key
        ) {
            if (
                !isset($security[$key])
                || !is_int($security[$key])
            ) {
                return false;
            }
        }

        return true;
    }

    private function setIni(
        string $key,
        string $value
    ): void {
        $current = ini_get($key);

        if (
            $current !== false
            && (string) $current === $value
        ) {
            return;
        }

        if (ini_set($key, $value) === false) {
            throw new RuntimeException(
                sprintf(
                    'Não foi possível configurar %s.',
                    $key
                )
            );
        }

        if ((string) ini_get($key) !== $value) {
            throw new RuntimeException(
                sprintf(
                    'A configuração %s não foi aplicada.',
                    $key
                )
            );
        }
    }

    private function requireStarted(): void
    {
        if (!$this->isStarted()) {
            throw new RuntimeException(
                'A sessão ainda não foi iniciada.'
            );
        }
    }
}
