<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Logging;

use Throwable;

final class ExceptionLogger
{
    public static function log(
        Throwable $exception,
        string $context
    ): void {
        $entries = [];
        $current = $exception;
        $depth = 0;

        while (
            $current instanceof Throwable
            && $depth < 5
        ) {
            $entries[] = sprintf(
                '%s: %s em %s:%d',
                $current::class,
                $current->getMessage(),
                $current->getFile(),
                $current->getLine()
            );

            $current = $current->getPrevious();
            $depth++;
        }

        error_log(
            sprintf(
                '[AGENDA][%s] %s',
                strtoupper($context),
                implode(
                    ' | causado por ',
                    $entries
                )
            )
        );
    }

    private function __construct()
    {
    }
}
