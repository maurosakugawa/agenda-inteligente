<?php

declare(strict_types=1);

use AgendaInteligente\Application\Contacts\ContactValidator;
use AgendaInteligente\Application\Contacts\InvalidContactInputException;

require_once dirname(__DIR__) . '/autoload.php';

/**
 * Verifica uma condição booleana.
 */
function assertContactValidatorTrue(
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException(
            $message
        );
    }
}

/**
 * Verifica se uma operação lança InvalidContactInputException.
 */
function assertContactValidatorRejects(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (InvalidContactInputException) {
        return;
    }

    throw new RuntimeException(
        $message
    );
}

$tests = [];

$tests[
    'aceita contato somente com nome'
] = static function (): void {
    $validator =
        new ContactValidator();

    $validator->validate(
        'Maria da Silva'
    );

    assertContactValidatorTrue(
        true,
        'Contato somente com nome deveria ser válido.'
    );
};

$tests[
    'aceita campos opcionais vazios'
] = static function (): void {
    $validator =
        new ContactValidator();

    $validator->validate(
        'Maria da Silva',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        ''
    );

    assertContactValidatorTrue(
        true,
        'Campos opcionais vazios deveriam ser válidos.'
    );
};

$tests[
    'aceita campos opcionais nulos'
] = static function (): void {
    $validator =
        new ContactValidator();

    $validator->validate(
        'Maria da Silva',
        null,
        null,
        null,
        null,
        null,
        null,
        null,
        null
    );

    assertContactValidatorTrue(
        true,
        'Campos opcionais nulos deveriam ser válidos.'
    );
};

$tests[
    'rejeita nome vazio'
] = static function (): void {
    $validator =
        new ContactValidator();

    assertContactValidatorRejects(
        static function () use (
            $validator
        ): void {
            $validator->validate('');
        },
        'Nome vazio deveria ser rejeitado.'
    );
};

$tests[
    'aceita limites máximos dos campos'
] = static function (): void {
    $validator =
        new ContactValidator();

    $validator->validate(
        str_repeat('a', 150),
        str_repeat('1', 30),
        str_repeat('a', 64)
            . '@'
            . str_repeat('b', 63)
            . '.'
            . str_repeat('c', 63)
            . '.'
            . str_repeat('d', 61),
        '12345-678',
        str_repeat('l', 190),
        str_repeat('2', 30),
        str_repeat('b', 100),
        str_repeat('c', 100),
        'SP'
    );

    assertContactValidatorTrue(
        true,
        'Valores exatamente nos limites deveriam ser válidos.'
    );
};

$tests[
    'aceita formatos válidos de e-mail cep e uf'
] = static function (): void {
    $validator =
        new ContactValidator();

    $validator->validate(
        'Maria da Silva',
        null,
        'maria.silva+agenda@example.com',
        '01310-100',
        null,
        null,
        null,
        null,
        'SP'
    );

    $validator->validate(
        'João da Silva',
        null,
        'joao@example.com',
        '01310100',
        null,
        null,
        null,
        null,
        'RJ'
    );

    assertContactValidatorTrue(
        true,
        'Formatos válidos de e-mail, CEP e UF deveriam ser aceitos.'
    );
};

$tests[
    'rejeita e-mail inválido'
] = static function (): void {
    $validator =
        new ContactValidator();

    assertContactValidatorRejects(
        static function () use (
            $validator
        ): void {
            $validator->validate(
                'Maria da Silva',
                null,
                'not-an-email'
            );
        },
        'E-mail com formato inválido deveria ser rejeitado.'
    );
};

$tests[
    'rejeita cep inválido'
] = static function (): void {
    $validator =
        new ContactValidator();

    $invalidCeps = [
        '1234-5678',
        '1234567',
        'ABCDE-12',
        '12345_67',
    ];

    foreach (
        $invalidCeps as $cep
    ) {
        assertContactValidatorRejects(
            static function () use (
                $validator,
                $cep
            ): void {
                $validator->validate(
                    'Maria da Silva',
                    null,
                    null,
                    $cep
                );
            },
            "CEP inválido {$cep} deveria ser rejeitado."
        );
    }
};

$tests[
    'rejeita uf inexistente'
] = static function (): void {
    $validator =
        new ContactValidator();

    assertContactValidatorRejects(
        static function () use (
            $validator
        ): void {
            $validator->validate(
                'Maria da Silva',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'XX'
            );
        },
        'UF inexistente deveria ser rejeitada.'
    );
};

$tests[
    'rejeita nome acima do limite'
] = static function (): void {
    $validator =
        new ContactValidator();

    assertContactValidatorRejects(
        static function () use (
            $validator
        ): void {
            $validator->validate(
                str_repeat('a', 151)
            );
        },
        'Nome acima de 150 caracteres deveria ser rejeitado.'
    );
};

$tests[
    'rejeita campos opcionais acima dos limites'
] = static function (): void {
    $validator =
        new ContactValidator();

    $invalidValues = [
        [
            'phone',
            [
                'Nome',
                str_repeat('1', 31),
            ],
        ],
        [
            'email',
            [
                'Nome',
                null,
                str_repeat('e', 255),
            ],
        ],
        [
            'cep',
            [
                'Nome',
                null,
                null,
                str_repeat('1', 10),
            ],
        ],
        [
            'logradouro',
            [
                'Nome',
                null,
                null,
                null,
                str_repeat('l', 191),
            ],
        ],
        [
            'numero',
            [
                'Nome',
                null,
                null,
                null,
                null,
                str_repeat('2', 31),
            ],
        ],
        [
            'bairro',
            [
                'Nome',
                null,
                null,
                null,
                null,
                null,
                str_repeat('b', 101),
            ],
        ],
        [
            'cidade',
            [
                'Nome',
                null,
                null,
                null,
                null,
                null,
                null,
                str_repeat('c', 101),
            ],
        ],
        [
            'uf',
            [
                'Nome',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'SPX',
            ],
        ],
    ];

    foreach (
        $invalidValues as [$field, $arguments]
    ) {
        assertContactValidatorRejects(
            static function () use (
                $validator,
                $arguments
            ): void {
                $validator->validate(
                    ...$arguments
                );
            },
            "Campo {$field} acima do limite deveria ser rejeitado."
        );
    }
};

$tests[
    'conta caracteres unicode em vez de bytes'
] = static function (): void {
    $validator =
        new ContactValidator();

    $validator->validate(
        str_repeat('á', 150)
    );

    assertContactValidatorRejects(
        static function () use (
            $validator
        ): void {
            $validator->validate(
                str_repeat('á', 151)
            );
        },
        'Limite de nome deveria considerar caracteres Unicode.'
    );
};

$tests[
    'rejeita utf8 inválido'
] = static function (): void {
    $validator =
        new ContactValidator();

    assertContactValidatorRejects(
        static function () use (
            $validator
        ): void {
            $validator->validate(
                "Contato \xC3\x28"
            );
        },
        'UTF-8 inválido deveria ser rejeitado.'
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
    "\n{$passed}/{$total} testes passaram.\n"
);

exit(
    $passed === $total
        ? 0
        : 1
);
