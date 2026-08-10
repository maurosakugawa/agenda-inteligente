<?php

declare(strict_types=1);

namespace AgendaInteligente\Infrastructure\Console;

use AgendaInteligente\Infrastructure\Config\ConfigLoader;
use AgendaInteligente\Infrastructure\Config\ConfigurationException;
use AgendaInteligente\Infrastructure\Database\Connection;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationException;
use AgendaInteligente\Infrastructure\Database\Migration\MigrationRunner;
use InvalidArgumentException;
use Throwable;

final class MigrationCommand
{
    private mixed $stdout;

    private mixed $stderr;

    public function __construct(
        private string $rootPath,
        private string $migrationsPath,
        mixed $stdout = null,
        mixed $stderr = null
    ) {
        $this->stdout =
            $stdout ?? STDOUT;

        $this->stderr =
            $stderr ?? STDERR;

        if (!is_resource($this->stdout)) {
            throw new InvalidArgumentException(
                'Saída padrão do comando de migrations é inválida.'
            );
        }

        if (!is_resource($this->stderr)) {
            throw new InvalidArgumentException(
                'Saída de erro do comando de migrations é inválida.'
            );
        }
    }

    public function run(): int
    {
        try {
            $loader =
                new ConfigLoader(
                    $this->rootPath
                );

            $config =
                $loader->load();

            $databaseConfig =
                $config['database'];

            if (!is_array($databaseConfig)) {
                throw new ConfigurationException(
                    'Configuração inválida: seção database deve ser um array.'
                );
            }

            $pdo =
                Connection::make(
                    $databaseConfig
                );

            $runner =
                new MigrationRunner(
                    $pdo,
                    $this->migrationsPath
                );

            $appliedCount =
                $runner->run();

            fwrite(
                $this->stdout,
                sprintf(
                    "Migrations concluídas: %d aplicada(s).\n",
                    $appliedCount
                )
            );

            return 0;
        } catch (ConfigurationException $exception) {
            fwrite(
                $this->stderr,
                $exception->getMessage()
                . PHP_EOL
            );

            return 1;
        } catch (MigrationException $exception) {
            fwrite(
                $this->stderr,
                sprintf(
                    "Falha ao executar migrations: %s\n",
                    $exception->getMessage()
                )
            );

            return 1;
        } catch (Throwable $exception) {
            error_log(
                sprintf(
                    '[AGENDA][MIGRATIONS] %s: %s',
                    $exception::class,
                    $exception->getMessage()
                )
            );

            fwrite(
                $this->stderr,
                "Falha inesperada ao executar migrations.\n"
            );

            return 1;
        }
    }
}
