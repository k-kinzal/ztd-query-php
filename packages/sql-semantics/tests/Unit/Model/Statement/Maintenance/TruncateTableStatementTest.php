<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Relation\TableReference;
use SqlSemantics\Model\Statement\Maintenance\TruncateTableStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TruncateTableStatement::class)]
#[Medium]
final class TruncateTableStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testWithOriginRetainsTheSingleTable(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t(id INTEGER)'));
        $statement = $binder->bind('TRUNCATE TABLE t');
        self::assertInstanceOf(TruncateTableStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertSame($statement->table, $copy->table);
        self::assertSame('TRUNCATE TABLE `t`', $copy->toString());
        self::assertSame($copy->toString(), $binder->bind($copy->toString())->toString());
    }

    public function testWithTableChangesTheTargetImmutably(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)', 'CREATE TABLE u(id INTEGER)'));
        $statement = $binder->bind('TRUNCATE t');
        $other = $binder->bind('TRUNCATE u');
        self::assertInstanceOf(TruncateTableStatement::class, $statement);
        self::assertInstanceOf(TruncateTableStatement::class, $other);
        $changed = $statement->withTable($other->table);
        self::assertSame('TRUNCATE TABLE `u`', $changed->toString());
        self::assertSame('TRUNCATE TABLE `t`', $statement->toString());
    }

    public function testRejectsAliasedTargets(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)')))->bind('SELECT a.id FROM t AS a');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(TableReference::class, $statement->from);
        $this->expectException(InvalidStructure::class);
        new TruncateTableStatement($statement->origin, $statement->from);
    }
}
