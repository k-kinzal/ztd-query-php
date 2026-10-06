<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;

#[CoversClass(CreateDatabase::class)]
#[Medium]
final class CreateDatabaseTest extends TestCase
{
    public function testRenderWritesIfNotExistsAndOptions(): void
    {
        self::assertSame('CREATE DATABASE IF NOT EXISTS d CHARACTER SET utf8mb4', (new Semantics(Dialect::MySql))->analyze('create database if not exists d charset utf8mb4')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE DATABASE d');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
