<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundQuery;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Model\Validation\SetOperands;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetOperands::class)]
#[Medium]
final class SetOperandsTest extends TestCase
{
    public function testCheckAcceptsPostgreSqlOperandsWithTheirOwnOrdering(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1 ORDER BY 1 LIMIT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        SetOperands::check(Dialect::PostgreSql, $query, $query);
        $this->addToAssertionCount(1);
    }

    public function testCheckAcceptsALeftAssociatedSqliteCompound(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $compound = $binder->bind('SELECT 1 UNION SELECT 2');
        $right = $binder->bind('SELECT 3');
        self::assertInstanceOf(BoundQuery::class, $compound);
        self::assertInstanceOf(BoundQuery::class, $right);
        SetOperands::check(Dialect::Sqlite, $compound, $right);
        $this->addToAssertionCount(1);
    }

    public function testCheckRejectsOperandsOfAnotherDialect(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundQuery::class, $query);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('Set operands must use the statement dialect.');
        SetOperands::check(Dialect::PostgreSql, $query, $query);
    }

    public function testCheckRejectsUnequalKnownWidths(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $left = $binder->bind('SELECT 1');
        $right = $binder->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundQuery::class, $left);
        self::assertInstanceOf(BoundQuery::class, $right);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('A set operation requires operands with equal result widths.');
        SetOperands::check(Dialect::Sqlite, $left, $right);
    }

    public function testCheckRejectsARightCompoundOperandInSqlite(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $left = $binder->bind('SELECT 1');
        $compound = $binder->bind('SELECT 1 UNION SELECT 2');
        self::assertInstanceOf(BoundQuery::class, $left);
        self::assertInstanceOf(BoundQuery::class, $compound);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('SQLite set operations associate to the left; a right compound operand requires an explicit derived relation.');
        SetOperands::check(Dialect::Sqlite, $left, $compound);
    }

    public function testCheckRejectsSqliteOperandsOwningAnOrdering(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::Sqlite))->build());
        $left = $binder->bind('SELECT 1');
        $ordered = $binder->bind('SELECT 2 ORDER BY 1');
        self::assertInstanceOf(BoundQuery::class, $left);
        self::assertInstanceOf(BoundQuery::class, $ordered);
        $this->expectException(InvalidStructure::class);
        $this->expectExceptionMessage('SQLite set operands cannot own WITH, ORDER BY or pagination clauses.');
        SetOperands::check(Dialect::Sqlite, $left, $ordered);
    }
}
