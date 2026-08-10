<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

interface AuthenticationSession
{
    /**
     * @param array{
     *     id:int,
     *     username:string
     * } $identity
     */
    public function establish(
        array $identity
    ): string;

    /**
     * @return array{
     *     id:int,
     *     username:string
     * }|null
     */
    public function current(): ?array;

    public function terminate(): void;
}
