<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database\Migration;

final class MigrationFile
{
    private const FILENAME_PATTERN =
        '/^(?<sequence>(?!000)[0-9]{3})_(?<description>[a-z0-9]+(?:_[a-z0-9]+)*)\.sql$/D';

    private string $filename;

    private string $version;

    private int $sequence;

    public function __construct(
        private string $path
    ) {
        $filename = basename(
            $path
        );

        $matches = [];

        if (
            preg_match(
                self::FILENAME_PATTERN,
                $filename,
                $matches
            ) !== 1
        ) {
            throw new MigrationException(
                sprintf(
                    'Nome de migration inválido: %s.',
                    $filename
                )
            );
        }

        $this->filename = $filename;
        $this->version = substr(
            $filename,
            0,
            -4
        );
        $this->sequence = (int) $matches['sequence'];
    }

    public function path(): string
    {
        return $this->path;
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function sequence(): int
    {
        return $this->sequence;
    }

    public function checksum(): string
    {
        if (
            !is_file(
                $this->path
            )
            || !is_readable(
                $this->path
            )
        ) {
            throw new MigrationException(
                sprintf(
                    'Arquivo de migration não pode ser lido: %s.',
                    $this->filename
                )
            );
        }

        $checksum = hash_file(
            'sha256',
            $this->path
        );

        if ($checksum === false) {
            throw new MigrationException(
                sprintf(
                    'Não foi possível calcular o checksum da migration: %s.',
                    $this->filename
                )
            );
        }

        return $checksum;
    }
}
