<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(CreateDatabase::class)]
#[Medium]
final class CreateDatabaseTest extends TestCase
{
    public function testRenderWritesIfNotExistsAndOptions(): void
    {
        self::assertSame('CREATE DATABASE IF NOT EXISTS d CHARACTER SET utf8mb4', (new Inspection())->inspect('create database if not exists d charset utf8mb4')->toString());
    }

    public function testDeriveStatementHasNoFacts(): void
    {
        $operation = (new Inspection())->inspect('CREATE DATABASE d');

        self::assertSame([], $operation->facts->diagnostics);
        self::assertNull($operation->facts->output);
    }
}
