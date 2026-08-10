<?php

declare(strict_types=1);

use AgendaInteligente\Application\Auth\CredentialValidator;
use AgendaInteligente\Application\Auth\InvalidCredentialsInputException;

require_once dirname(__DIR__) . '/autoload.php';

function assertCredentialValidatorThrows(
    callable $callback,
    string $message
): void {
    try {
        $callback();
    } catch (InvalidCredentialsInputException) {
        return;
    }

    throw new RuntimeException(
        $message
    );
}

$validator =
    new CredentialValidator();

$tests = [];

$tests[
    'registro aceita credenciais válidas'
] = static function () use (
    $validator
): void {
    $validator->validateForRegistration(
        'mauro.sakugawa',
        'uma-senha-longa-segura'
    );
};

$tests[
    'registro aceita username Unicode e espaço interno'
] = static function () use (
    $validator
): void {
    $validator->validateForRegistration(
        'João da Silva',
        'senha Unicode áéíóú 123'
    );
};

$tests[
    'registro rejeita username vazio'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                '',
                'uma-senha-longa-segura'
            ),
        'Username vazio deveria ser rejeitado.'
    );
};

$tests[
    'registro rejeita username menor que três caracteres'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                'ab',
                'uma-senha-longa-segura'
            ),
        'Username com menos de três caracteres deveria ser rejeitado.'
    );
};

$tests[
    'registro rejeita username acima de cem caracteres'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                str_repeat(
                    'a',
                    101
                ),
                'uma-senha-longa-segura'
            ),
        'Username acima de cem caracteres deveria ser rejeitado.'
    );
};

$tests[
    'registro rejeita espaço no início do username'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                ' usuario',
                'uma-senha-longa-segura'
            ),
        'Username com espaço inicial deveria ser rejeitado.'
    );
};

$tests[
    'registro rejeita espaço no fim do username'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                'usuario ',
                'uma-senha-longa-segura'
            ),
        'Username com espaço final deveria ser rejeitado.'
    );
};

$tests[
    'registro rejeita caractere de controle no username'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                "usuario\nnome",
                'uma-senha-longa-segura'
            ),
        'Username com caractere de controle deveria ser rejeitado.'
    );
};

$tests[
    'registro rejeita UTF-8 inválido no username'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                "usuario\xFF",
                'uma-senha-longa-segura'
            ),
        'Username com UTF-8 inválido deveria ser rejeitado.'
    );
};

$tests[
    'registro rejeita senha vazia'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                'usuario',
                ''
            ),
        'Senha vazia deveria ser rejeitada.'
    );
};

$tests[
    'registro rejeita senha com menos de quinze caracteres'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                'usuario',
                'curta-demais'
            ),
        'Senha com menos de quinze caracteres deveria ser rejeitada.'
    );
};

$tests[
    'registro conta caracteres Unicode no mínimo da senha'
] = static function () use (
    $validator
): void {
    $validator->validateForRegistration(
        'usuario',
        str_repeat(
            'á',
            15
        )
    );
};

$tests[
    'registro rejeita senha acima de setenta e dois bytes'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForRegistration(
                'usuario',
                str_repeat(
                    'á',
                    37
                )
            ),
        'Senha acima de 72 bytes deveria ser rejeitada.'
    );
};

$tests[
    'login aceita username e senha legados curtos'
] = static function () use (
    $validator
): void {
    $validator->validateForLogin(
        'a',
        'x'
    );
};

$tests[
    'login rejeita username vazio'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForLogin(
                '',
                'senha'
            ),
        'Login deveria rejeitar username vazio.'
    );
};

$tests[
    'login rejeita username acima de cem caracteres'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForLogin(
                str_repeat(
                    'a',
                    101
                ),
                'senha'
            ),
        'Login deveria rejeitar username acima de cem caracteres.'
    );
};

$tests[
    'login rejeita espaço indevido no username'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForLogin(
                ' usuario',
                'senha'
            ),
        'Login deveria rejeitar username com espaço inicial.'
    );
};

$tests[
    'login rejeita caractere de controle no username'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForLogin(
                "usuario\tinvalido",
                'senha'
            ),
        'Login deveria rejeitar caractere de controle no username.'
    );
};

$tests[
    'login rejeita senha vazia'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForLogin(
                'usuario',
                ''
            ),
        'Login deveria rejeitar senha vazia.'
    );
};

$tests[
    'login rejeita senha acima de setenta e dois bytes'
] = static function () use (
    $validator
): void {
    assertCredentialValidatorThrows(
        static fn () =>
            $validator->validateForLogin(
                'usuario',
                str_repeat(
                    'a',
                    73
                )
            ),
        'Login deveria rejeitar senha acima de 72 bytes.'
    );
};

$passed = 0;
$total = count($tests);

foreach (
    $tests as $name => $test
) {
    try {
        $test();

        ++$passed;

        fwrite(
            STDOUT,
            "[OK] {$name}\n"
        );
    } catch (Throwable $exception) {
        fwrite(
            STDERR,
            "[ERRO] {$name}: "
            . $exception->getMessage()
            . "\n"
        );
    }
}

fwrite(
    STDOUT,
    "\nCredentialValidator: "
    . "{$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
