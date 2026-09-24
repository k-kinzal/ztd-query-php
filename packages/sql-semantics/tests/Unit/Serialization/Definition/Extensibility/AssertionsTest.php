<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Extensibility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Extensibility\Assertions;

#[CoversClass(Assertions::class)]
#[Medium]
final class AssertionsTest extends TestCase
{
    #[TestWith(['CREATE ASSERTION a CHECK (1 < 2) NOT DEFERRABLE', 'CREATE ASSERTION "a" CHECK ((1 < 2))'])]
    #[TestWith(['CREATE ASSERTION a CHECK (true) DEFERRABLE INITIALLY IMMEDIATE', 'CREATE ASSERTION "a" CHECK (true) DEFERRABLE'])]
    #[TestWith(['CREATE ASSERTION a CHECK (true) INITIALLY DEFERRED', 'CREATE ASSERTION "a" CHECK (true) DEFERRABLE INITIALLY DEFERRED'])]
    public function testWriteSpellsTheCheckingTime(string $sql, string $expected): void
    {
        self::assertSame($expected, Assertions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql))?->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(Assertions::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }
}
