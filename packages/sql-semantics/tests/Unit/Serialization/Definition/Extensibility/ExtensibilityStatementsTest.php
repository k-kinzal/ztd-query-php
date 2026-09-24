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
use SqlSemantics\Serialization\Definition\Extensibility\ExtensibilityStatements;

#[CoversClass(ExtensibilityStatements::class)]
#[Medium]
final class ExtensibilityStatementsTest extends TestCase
{
    #[TestWith(['CREATE EXTENSION e', 'CREATE EXTENSION "e"'])]
    #[TestWith(['ALTER STATISTICS s SET STATISTICS 1', 'ALTER STATISTICS "s" SET STATISTICS 1'])]
    #[TestWith(['CREATE ASSERTION a CHECK (true)', 'CREATE ASSERTION "a" CHECK (true)'])]
    #[TestWith(['CREATE SEQUENCE s', 'CREATE SEQUENCE "s"'])]
    #[TestWith(['CREATE FUNCTION f() RETURNS integer RETURN 1', 'CREATE FUNCTION "f"() RETURNS integer LANGUAGE "sql" RETURN 1'])]
    #[TestWith(['ALTER FUNCTION f() STABLE', 'ALTER FUNCTION "f"() STABLE'])]
    public function testWriteRoutesEachFamily(string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)'));
        self::assertSame($expected, ExtensibilityStatements::write($binder->bind($sql))?->toString());
    }

    public function testWriteIgnoresOtherStatements(): void
    {
        self::assertNull(ExtensibilityStatements::write((new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1')));
    }
}
