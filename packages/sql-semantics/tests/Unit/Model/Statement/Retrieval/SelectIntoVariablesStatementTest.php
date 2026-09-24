<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Retrieval;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\Retrieval\SelectIntoVariablesStatement;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SelectIntoVariablesStatement::class)]
#[Medium]
final class SelectIntoVariablesStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheReceivingUserVariablesOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('SELECT a, b INTO @first, @`Second` FROM t WHERE a > 0 FOR UPDATE');
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $statement);
        self::assertSame(['first', 'Second'], $statement->variables);
        self::assertSame(StatementKind::Select, $statement->kind);
        self::assertSame('SELECT `a` AS `a`, `b` AS `b` FROM `t` WHERE (`a` > 0) INTO @`first`, @`Second` FOR UPDATE', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithVariablesReplacesTheTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT a INTO @x FROM t');
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $statement);
        $changed = $statement->withVariables(['y']);
        self::assertNotSame($statement, $changed);
        self::assertSame(['y'], $changed->variables);
        self::assertSame(['x'], $statement->variables);
        self::assertSame('SELECT `a` AS `a` FROM `t` INTO @`y`', $changed->toString());
    }

    public function testWithQueryReplacesTheStoredQuery(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)'));
        $statement = $binder->bind('SELECT a INTO @x FROM t');
        $query = $binder->bind('SELECT b FROM t');
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $statement);
        self::assertInstanceOf(BoundSelect::class, $query);
        $changed = $statement->withQuery($query);
        self::assertSame('SELECT `b` AS `b` FROM `t` INTO @`x`', $changed->toString());
        self::assertSame('SELECT `a` AS `a` FROM `t` INTO @`x`', $statement->toString());
    }

    public function testWithOriginKeepsTheOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1 INTO @x');
        self::assertInstanceOf(SelectIntoVariablesStatement::class, $statement);
        $copy = $statement->withOrigin(new Origin('other', $statement->source, Dialect::MySql));
        self::assertSame('other', $copy->scopeId);
        self::assertSame([$statement->query, ['x']], [$copy->query, $copy->variables]);
    }

    public function testRejectsAVariableCountDifferentFromTheColumns(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1, 2');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new SelectIntoVariablesStatement(new Origin('s', $query->source, Dialect::MySql), $query, ['x']);
    }

    public function testRejectsAPostgreSqlQuery(): void
    {
        $query = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT 1');
        self::assertInstanceOf(BoundSelect::class, $query);
        $this->expectException(InvalidStructure::class);
        new SelectIntoVariablesStatement(new Origin('s', $query->source, Dialect::PostgreSql), $query, ['x']);
    }
}
