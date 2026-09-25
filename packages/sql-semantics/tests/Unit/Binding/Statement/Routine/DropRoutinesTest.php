<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Statement\Definition\MySql;
use SqlSemantics\Model\Statement\Definition\PostgreSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Routine\DropRoutines::class)]
#[Medium]
final class DropRoutinesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindMySqlFunctionRetainsOneQualifiedNameAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('DROP FUNCTION IF EXISTS app.f');
        self::assertInstanceOf(MySql\DropFunctionStatement::class, $statement);
        self::assertSame(['app', 'f'], $statement->name->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame('DROP FUNCTION IF EXISTS `app`.`f`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindMySqlProcedureRetainsOneQualifiedNameAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('DROP PROCEDURE IF EXISTS app.f');
        self::assertInstanceOf(MySql\DropProcedureStatement::class, $statement);
        self::assertSame(['app', 'f'], $statement->name->parts);
        self::assertTrue($statement->ifExists);
        self::assertSame('DROP PROCEDURE IF EXISTS `app`.`f`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindPostgreSqlFunctionKeepsOverloadSelectionAndDeletionPolicies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP FUNCTION IF EXISTS f, g(), app.h(IN id integer, OUT message text) CASCADE');
        self::assertInstanceOf(PostgreSql\DropFunctionsStatement::class, $statement);
        self::assertTrue($statement->ifExists);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Cascade, $statement->behavior);
        self::assertInstanceOf(Routine\RoutineByName::class, $statement->targets[0]);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[1]);
        self::assertSame([], $statement->targets[1]->parameters);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[2]);
        self::assertSame(['app', 'h'], $statement->targets[2]->name->parts);
        self::assertSame(Routine\ParameterMode::Input, $statement->targets[2]->parameters[0]->mode);
        self::assertSame(Routine\ParameterMode::Output, $statement->targets[2]->parameters[1]->mode);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindPostgreSqlProcedureKeepsOverloadSelectionAndDeletionPolicies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP PROCEDURE IF EXISTS f, g(), app.h(IN id integer, OUT message text) CASCADE');
        self::assertInstanceOf(PostgreSql\DropProceduresStatement::class, $statement);
        self::assertTrue($statement->ifExists);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Cascade, $statement->behavior);
        self::assertInstanceOf(Routine\RoutineByName::class, $statement->targets[0]);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[1]);
        self::assertSame([], $statement->targets[1]->parameters);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[2]);
        self::assertSame(['app', 'h'], $statement->targets[2]->name->parts);
        self::assertSame(Routine\ParameterMode::Input, $statement->targets[2]->parameters[0]->mode);
        self::assertSame(Routine\ParameterMode::Output, $statement->targets[2]->parameters[1]->mode);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testBindPostgreSqlRoutineKeepsOverloadSelectionAndDeletionPolicies(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP ROUTINE IF EXISTS f, g(), app.h(IN id integer, OUT message text) CASCADE');
        self::assertInstanceOf(PostgreSql\DropRoutinesStatement::class, $statement);
        self::assertTrue($statement->ifExists);
        self::assertSame(\SqlSemantics\Model\Definition\DropBehavior::Cascade, $statement->behavior);
        self::assertInstanceOf(Routine\RoutineByName::class, $statement->targets[0]);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[1]);
        self::assertSame([], $statement->targets[1]->parameters);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[2]);
        self::assertSame(['app', 'h'], $statement->targets[2]->name->parts);
        self::assertSame(Routine\ParameterMode::Input, $statement->targets[2]->parameters[0]->mode);
        self::assertSame(Routine\ParameterMode::Output, $statement->targets[2]->parameters[1]->mode);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith([Dialect::PostgreSql, 'drop aggregate a(int), b(*) cascade', PostgreSql\DropAggregatesStatement::class, 'DROP AGGREGATE "a"(integer), "b"(*) CASCADE'])]
    #[TestWith([Dialect::PostgreSql, 'DROP AGGREGATE IF EXISTS a(int)', PostgreSql\DropAggregatesStatement::class, 'DROP AGGREGATE IF EXISTS "a"(integer)'])]
    #[TestWith([Dialect::PostgreSql, 'drop function f(int) restrict', PostgreSql\DropFunctionsStatement::class, 'DROP FUNCTION "f"(integer) RESTRICT'])]
    #[TestWith([Dialect::PostgreSql, 'drop procedure p', PostgreSql\DropProceduresStatement::class, 'DROP PROCEDURE "p"'])]
    #[TestWith([Dialect::PostgreSql, 'drop routine r', PostgreSql\DropRoutinesStatement::class, 'DROP ROUTINE "r"'])]
    #[TestWith([Dialect::MySql, 'drop function f', MySql\DropFunctionStatement::class, 'DROP FUNCTION `f`'])]
    public function testBindReadsLowerCaseRemovals(Dialect $dialect, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
