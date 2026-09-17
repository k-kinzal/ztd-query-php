<?php

declare(strict_types=1);

namespace Bench;

use Container\MySql80Container;
use Container\PostgreSql16Container;
use PhpBench\Attributes as Bench;

/**
 * Measures definition construction separately from Docker startup and network I/O.
 */
#[Bench\Revs(1000)]
final class ContainerDefinitionBench
{
    /**
     * Constructs a MySQL definition and its readiness strategy.
     *
     * @throws \Testcontainers\Exceptions\InvalidFormatException If a configured mount cannot be parsed.
     */
    public function benchMySqlDefinition(): void
    {
        new MySql80Container();
    }

    /**
     * Constructs a PostgreSQL definition and its readiness strategy.
     */
    public function benchPostgreSqlDefinition(): void
    {
        new PostgreSql16Container();
    }
}
