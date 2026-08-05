<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Connection
{
    private static ?PDO $instance = null;

    /**
     * @param array{host:string,port:int,database:string,username:string,password:string,charset:string} $config
     */
    public static function make(array $config): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        try {
            self::$instance = new PDO(
                $dsn,
                $config['username'],
                $config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_STRINGIFY_FETCHES => false,
                ]
            );
        } catch (PDOException $exception) {
            throw new RuntimeException(
                'Não foi possível estabelecer conexão com o banco de dados.',
                0,
                $exception
            );
        }

        return self::$instance;
    }

    private function __construct()
    {
    }
}
