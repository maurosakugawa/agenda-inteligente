<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Contacts;

final class ContactValidator
{
    private const NAME_MAX_CHARACTERS = 150;

    private const PHONE_MAX_CHARACTERS = 30;

    private const EMAIL_MAX_CHARACTERS = 254;

    private const CEP_MAX_CHARACTERS = 9;

    private const LOGRADOURO_MAX_CHARACTERS = 190;

    private const NUMERO_MAX_CHARACTERS = 30;

    private const BAIRRO_MAX_CHARACTERS = 100;

    private const CIDADE_MAX_CHARACTERS = 100;

    private const UF_MAX_CHARACTERS = 2;

    private const VALID_UFS = [
        'AC',
        'AL',
        'AP',
        'AM',
        'BA',
        'CE',
        'DF',
        'ES',
        'GO',
        'MA',
        'MT',
        'MS',
        'MG',
        'PA',
        'PB',
        'PR',
        'PE',
        'PI',
        'RJ',
        'RN',
        'RS',
        'RO',
        'RR',
        'SC',
        'SP',
        'SE',
        'TO',
    ];

    public function validate(
        string $name,
        ?string $phone = null,
        ?string $email = null,
        ?string $cep = null,
        ?string $logradouro = null,
        ?string $numero = null,
        ?string $bairro = null,
        ?string $cidade = null,
        ?string $uf = null
    ): void {
        if ($name === '') {
            throw new InvalidContactInputException(
                'Nome do contato é obrigatório.'
            );
        }

        $this->validateRequiredString(
            $name,
            self::NAME_MAX_CHARACTERS,
            'Nome'
        );

        $this->validateOptionalString(
            $phone,
            self::PHONE_MAX_CHARACTERS,
            'Telefone'
        );

        $this->validateOptionalString(
            $email,
            self::EMAIL_MAX_CHARACTERS,
            'E-mail'
        );

        $this->validateEmail(
            $email
        );

        $this->validateOptionalString(
            $cep,
            self::CEP_MAX_CHARACTERS,
            'CEP'
        );

        $this->validateCep(
            $cep
        );

        $this->validateOptionalString(
            $logradouro,
            self::LOGRADOURO_MAX_CHARACTERS,
            'Logradouro'
        );

        $this->validateOptionalString(
            $numero,
            self::NUMERO_MAX_CHARACTERS,
            'Número'
        );

        $this->validateOptionalString(
            $bairro,
            self::BAIRRO_MAX_CHARACTERS,
            'Bairro'
        );

        $this->validateOptionalString(
            $cidade,
            self::CIDADE_MAX_CHARACTERS,
            'Cidade'
        );

        $this->validateOptionalString(
            $uf,
            self::UF_MAX_CHARACTERS,
            'UF'
        );

        $this->validateUf(
            $uf
        );
    }

    private function validateRequiredString(
        string $value,
        int $maximumCharacters,
        string $field
    ): void {
        if (!$this->isValidUtf8($value)) {
            throw new InvalidContactInputException(
                "{$field} deve possuir UTF-8 válido."
            );
        }

        if (
            $this->unicodeLength($value)
            > $maximumCharacters
        ) {
            throw new InvalidContactInputException(
                "{$field} excede o tamanho máximo permitido."
            );
        }
    }

    private function validateOptionalString(
        ?string $value,
        int $maximumCharacters,
        string $field
    ): void {
        if ($value === null) {
            return;
        }

        $this->validateRequiredString(
            $value,
            $maximumCharacters,
            $field
        );
    }

    private function validateEmail(
        ?string $email
    ): void {
        if (
            $email === null
            || $email === ''
        ) {
            return;
        }

        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidContactInputException(
                'E-mail possui formato inválido.'
            );
        }
    }

    private function validateCep(
        ?string $cep
    ): void {
        if (
            $cep === null
            || $cep === ''
        ) {
            return;
        }

        if (
            preg_match(
                '/^[0-9]{5}-?[0-9]{3}$/D',
                $cep
            ) !== 1
        ) {
            throw new InvalidContactInputException(
                'CEP possui formato inválido.'
            );
        }
    }

    private function validateUf(
        ?string $uf
    ): void {
        if (
            $uf === null
            || $uf === ''
        ) {
            return;
        }

        if (
            !in_array(
                $uf,
                self::VALID_UFS,
                true
            )
        ) {
            throw new InvalidContactInputException(
                'UF inválida.'
            );
        }
    }

    private function isValidUtf8(
        string $value
    ): bool {
        return preg_match(
            '//u',
            $value
        ) === 1;
    }

    private function unicodeLength(
        string $value
    ): int {
        $result =
            preg_match_all(
                '/./us',
                $value
            );

        if ($result === false) {
            throw new InvalidContactInputException(
                'Valor contém UTF-8 inválido.'
            );
        }

        return $result;
    }
}
