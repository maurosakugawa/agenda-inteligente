<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

interface CurrentUserProvider
{
    /**
     * @return array{
     *     id:int,
     *     username:string
     * }|null
     */
    public function resolve(): ?array;
}
