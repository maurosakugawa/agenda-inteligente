<?php

declare(strict_types=1);

namespace AgendaInteligente\Application\Auth;

final class CredentialValidator
{
    private const REGISTRATION_USERNAME_MIN_CHARACTERS = 3;

    private const USERNAME_MAX_CHARACTERS = 100;

    private const REGISTRATION_PASSWORD_MIN_CHARACTERS = 15;

    private const PASSWORD_MAX_BYTES = 72;

    public function validateForRegistration(
        string $username,
        string $password
    ): void {
        $this->validateUsername(
            $username,
            true
        );

        $this->validateRegistrationPassword(
            $password
        );
    }

    public function validateForLogin(
        string $username,
        string $password
    ): void {
        $this->validateUsername(
            $username,
            false
        );

        $this->validateLoginPassword(
            $password
        );
    }

    private function validateUsername(
        string $username,
        bool $registration
    ): void {
        if ($username === '') {
            throw new InvalidCredentialsInputException(
                'Nome de usuário é obrigatório.'
            );
        }

        if (!$this->isValidUtf8($username)) {
            throw new InvalidCredentialsInputException(
                'Nome de usuário deve possuir UTF-8 válido.'
            );
        }

        if ($this->containsControlCharacter($username)) {
            throw new InvalidCredentialsInputException(
                'Nome de usuário não pode conter caracteres de controle.'
            );
        }

        if ($this->hasBoundaryWhitespace($username)) {
            throw new InvalidCredentialsInputException(
                'Nome de usuário não pode começar ou terminar com espaços.'
            );
        }

        $length =
            $this->unicodeLength(
                $username
            );

        if (
            $registration
            && $length
                < self::REGISTRATION_USERNAME_MIN_CHARACTERS
        ) {
            throw new InvalidCredentialsInputException(
                'Nome de usuário deve possuir entre 3 e 100 caracteres.'
            );
        }

        if (
            $length
            > self::USERNAME_MAX_CHARACTERS
        ) {
            if ($registration) {
                throw new InvalidCredentialsInputException(
                    'Nome de usuário deve possuir entre 3 e 100 caracteres.'
                );
            }

            throw new InvalidCredentialsInputException(
                'Nome de usuário deve possuir no máximo 100 caracteres.'
            );
        }
    }

    private function validateRegistrationPassword(
        string $password
    ): void {
        if ($password === '') {
            throw new InvalidCredentialsInputException(
                'Senha é obrigatória.'
            );
        }

        if (!$this->isValidUtf8($password)) {
            throw new InvalidCredentialsInputException(
                'Senha deve possuir UTF-8 válido.'
            );
        }

        if (
            $this->unicodeLength($password)
            < self::REGISTRATION_PASSWORD_MIN_CHARACTERS
        ) {
            throw new InvalidCredentialsInputException(
                'Senha deve possuir no mínimo 15 caracteres.'
            );
        }

        $this->validatePasswordMaximumLength(
            $password
        );
    }

    private function validateLoginPassword(
        string $password
    ): void {
        if ($password === '') {
            throw new InvalidCredentialsInputException(
                'Senha é obrigatória.'
            );
        }

        $this->validatePasswordMaximumLength(
            $password
        );
    }

    private function validatePasswordMaximumLength(
        string $password
    ): void {
        if (
            strlen($password)
            > self::PASSWORD_MAX_BYTES
        ) {
            throw new InvalidCredentialsInputException(
                'Senha deve possuir no máximo 72 bytes.'
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

    private function containsControlCharacter(
        string $value
    ): bool {
        return preg_match(
            '/\p{Cc}/u',
            $value
        ) === 1;
    }

    private function hasBoundaryWhitespace(
        string $value
    ): bool {
        return preg_match(
            '/^(?:\p{Z}|\h|\v)|(?:\p{Z}|\h|\v)$/u',
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
            throw new InvalidCredentialsInputException(
                'Valor contém UTF-8 inválido.'
            );
        }

        return $result;
    }
}
