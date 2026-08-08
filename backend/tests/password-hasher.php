<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Security\PasswordHasher;

require_once dirname(__DIR__) . '/autoload.php';

function assertPasswordHasherTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

function assertPasswordHasherSame(
    mixed $expected,
    mixed $actual,
    string $message
): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            $message
            . ' Esperado: '
            . var_export(
                $expected,
                true
            )
            . '; obtido: '
            . var_export(
                $actual,
                true
            )
        );
    }
}

$tests = [];

$tests[
    'gera hash compatível com PASSWORD_DEFAULT'
] = static function (): void {
    $hasher =
        new PasswordHasher();

    $password =
        'senha-segura-de-teste';

    $hash =
        $hasher->hash(
            $password
        );

    assertPasswordHasherTrue(
        $hash !== '',
        'Hash não deveria ser vazio.'
    );

    assertPasswordHasherTrue(
        $hash !== $password,
        'Senha não pode ser armazenada em texto puro.'
    );

    assertPasswordHasherSame(
        false,
        password_needs_rehash(
            $hash,
            PASSWORD_DEFAULT
        ),
        'Hash recém-gerado deveria estar compatível com PASSWORD_DEFAULT.'
    );
};

$tests[
    'valida senha correta'
] = static function (): void {
    $hasher =
        new PasswordHasher();

    $password =
        'senha-correta';

    $hash =
        $hasher->hash(
            $password
        );

    assertPasswordHasherTrue(
        $hasher->verify(
            $password,
            $hash
        ),
        'Senha correta deveria ser validada.'
    );
};

$tests[
    'rejeita senha incorreta'
] = static function (): void {
    $hasher =
        new PasswordHasher();

    $hash =
        $hasher->hash(
            'senha-correta'
        );

    assertPasswordHasherSame(
        false,
        $hasher->verify(
            'senha-incorreta',
            $hash
        ),
        'Senha incorreta deveria ser rejeitada.'
    );
};

$tests[
    'gera salt diferente para hashes da mesma senha'
] = static function (): void {
    $hasher =
        new PasswordHasher();

    $password =
        'mesma-senha';

    $firstHash =
        $hasher->hash(
            $password
        );

    $secondHash =
        $hasher->hash(
            $password
        );

    assertPasswordHasherTrue(
        $firstHash !== $secondHash,
        'Hashes da mesma senha deveriam usar salts diferentes.'
    );

    assertPasswordHasherTrue(
        $hasher->verify(
            $password,
            $firstHash
        ),
        'Primeiro hash deveria validar a senha.'
    );

    assertPasswordHasherTrue(
        $hasher->verify(
            $password,
            $secondHash
        ),
        'Segundo hash deveria validar a senha.'
    );
};

$tests[
    'rejeita hash inválido'
] = static function (): void {
    $hasher =
        new PasswordHasher();

    assertPasswordHasherSame(
        false,
        $hasher->verify(
            'qualquer-senha',
            'hash-invalido'
        ),
        'Hash inválido deveria ser rejeitado.'
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
    "\nPasswordHasher: {$passed}/{$total} teste(s) aprovado(s).\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
