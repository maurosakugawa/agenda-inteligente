<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Persistence;

use AgendaInteligente\Application\Auth\UsernameCanonicalizer;
use PDO;
use RuntimeException;

/**
 * Canonicaliza usernames segundo a mesma collation
 * usada pela coluna users.username.
 *
 * WEIGHT_STRING() produz o mesmo peso de comparação
 * para valores equivalentes por utf8mb4_unicode_ci;
 * HEX() converte esse peso em uma representação
 * textual estável para uso interno.
 */
final class MySqlUsernameCanonicalizer
    implements UsernameCanonicalizer
{
    /**
     * @var array<string, string>
     */
    private array $cache = [];

    public function __construct(
        private PDO $pdo
    ) {
    }

    public function canonicalize(
        string $username
    ): string {
        if (
            array_key_exists(
                $username,
                $this->cache
            )
        ) {
            return $this->cache[
                $username
            ];
        }

        $statement =
            $this->pdo->prepare(
                "
                SELECT HEX(
                    WEIGHT_STRING(
                        CONVERT(
                            :username
                            USING utf8mb4
                        )
                        COLLATE utf8mb4_unicode_ci
                    )
                )
                "
            );

        $statement->execute([
            ':username' => $username,
        ]);

        $canonical =
            $statement->fetchColumn();

        if (!is_string($canonical)) {
            throw new RuntimeException(
                'Não foi possível canonicalizar o username.'
            );
        }

        $this->cache[
            $username
        ] = $canonical;

        return $canonical;
    }
}
