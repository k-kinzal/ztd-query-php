<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Mutation\DeleteJoinedStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DeleteJoinedStatement::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DeleteJoinedStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.4.7'])]
    public function testAffectedTablesPreservesQualifiedUnresolvedDestinations(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('DELETE FROM tenant.t, t USING other_table', false);
        self::assertInstanceOf(DeleteJoinedStatement::class, $statement);
        self::assertSame(['tenant', 't'], $statement->targets[0]->name->parts);
        self::assertSame($statement->targets, $statement->affectedTables());
        self::assertSame('DELETE `tenant`.`t`, `t` FROM `other_table`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $copy = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement), false);
        self::assertInstanceOf(DeleteJoinedStatement::class, $copy);
        self::assertSame(['tenant', 't'], $copy->targets[0]->name->parts);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($copy));
    }

    public function testAffectedTablesRetainsAnAliasInsteadOfTheHiddenTableName(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE tenant.t(id INTEGER)');
        $statement = (new Binder($schema))->bind('DELETE a FROM tenant.t AS a');
        self::assertInstanceOf(DeleteJoinedStatement::class, $statement);
        self::assertSame('DELETE `a` FROM `tenant`.`t` AS `a`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame('a', $statement->targets[0]->alias);
    }

    public function testWithOriginRetainsDestinationsAndReadRelations(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INTEGER)');
        $statement = (new Binder($schema))->bind('DELETE t FROM t');
        self::assertInstanceOf(DeleteJoinedStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->targets, $copy->targets);
        self::assertSame($statement->from, $copy->from);
    }

    public function testWithWhereRetainsTheQualifiedDestination(): void
    {
        $schema = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE tenant.t(id INTEGER)');
        $statement = (new Binder($schema))->bind('DELETE tenant.t FROM tenant.t');
        self::assertInstanceOf(DeleteJoinedStatement::class, $statement);
        $copy = $statement->withWhere(Expression::literal(true, Dialect::MySql));
        self::assertSame('DELETE `tenant`.`t` FROM `tenant`.`t` WHERE TRUE', (new \SqlSemantics\SimpleSerializer())->serialize($copy));
        self::assertNull($statement->where);
    }
}
