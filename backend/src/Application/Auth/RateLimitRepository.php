<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

interface RateLimitRepository
{
    /**
     * Retorna o instante Unix até o qual o bucket está bloqueado.
     *
     * Retorna null quando não existe bloqueio ativo no instante informado.
     */
    public function blockedUntil(
        string $scope,
        string $keyHash,
        int $now
    ): ?int;

    /**
     * Registra atomicamente uma tentativa no bucket.
     *
     * A implementação deve:
     *
     * - respeitar a janela configurada;
     * - reiniciar a contagem quando a janela expirar;
     * - incrementar a contagem sem perder atualizações concorrentes;
     * - armar o bloqueio quando a tentativa atingir o limite;
     * - não incrementar quando o bucket já estiver bloqueado.
     *
     * O limite representa a quantidade de tentativas permitidas.
     * Portanto, a tentativa que atinge o limite ainda é permitida,
     * mas deixa o bucket bloqueado para tentativas subsequentes.
     *
     * Retorna o instante Unix do bloqueio quando a tentativa atual
     * já deve ser impedida, ou null quando ela pode prosseguir.
     */
    public function recordAttempt(
        string $scope,
        string $keyHash,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds,
        int $now
    ): ?int;

    /**
     * Remove o estado persistido do bucket.
     */
    public function clear(
        string $scope,
        string $keyHash
    ): void;
}
