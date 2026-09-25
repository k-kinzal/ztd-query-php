<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Inspection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Inspection\Definitions;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Routine\RoutineKind;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateDatabaseStatement;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateProcedureStatement;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateUserStatement;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowRoutineCodeStatement;
use SqlSemantics\Model\Statement\Inspection\Definition\ShowRoutineStatusStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Definitions::class)]
#[Medium]
final class DefinitionsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindKeepsTheRoutineKindAndConditionAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("SHOW /* routines */ PROCEDURE STATUS WHERE Db = 'app'");
        self::assertInstanceOf(ShowRoutineStatusStatement::class, $statement);
        self::assertSame(RoutineKind::Procedure, $statement->routine);
        self::assertInstanceOf(ConditionFilter::class, $statement->filter);
        self::assertSame("SHOW PROCEDURE STATUS WHERE (`Db` = 'app')", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testCreateKeepsEachObjectKindInItsOwnNameDomain(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $database = $binder->bind('SHOW CREATE SCHEMA IF NOT EXISTS `my db`');
        $procedure = $binder->bind('SHOW CREATE PROCEDURE `my db`.sync');
        $user = $binder->bind("SHOW CREATE USER 'app'@'%'");
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $database);
        self::assertInstanceOf(ShowCreateProcedureStatement::class, $procedure);
        self::assertInstanceOf(ShowCreateUserStatement::class, $user);
        self::assertSame('my db', $database->database);
        self::assertTrue($database->ifNotExists);
        self::assertSame(['my db', 'sync'], $procedure->procedure->parts);
        self::assertInstanceOf(\SqlSemantics\Model\Configuration\Account\AccountName::class, $user->account);
        self::assertSame('%', $user->account->host);
        self::assertSame('SHOW CREATE DATABASE IF NOT EXISTS `my db`', (new \SqlSemantics\SimpleSerializer())->serialize($database));
    }

    public function testRoutineNameUnquotesEachPart(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW FUNCTION CODE `a``b`.`c d`');
        self::assertInstanceOf(ShowRoutineCodeStatement::class, $statement);
        self::assertSame(['a`b', 'c d'], $statement->name->parts);
        self::assertSame('SHOW FUNCTION CODE `a``b`.`c d`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testDatabaseNameUnquotesTheIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE DATABASE `a``b`');
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $statement);
        self::assertSame('a`b', $statement->database);
        self::assertSame('SHOW CREATE DATABASE `a``b`', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[TestWith(['show create event e', \SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateEventStatement::class, 'SHOW CREATE EVENT `e`'])]
    #[TestWith(['show create function d.f', \SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateFunctionStatement::class, 'SHOW CREATE FUNCTION `d`.`f`'])]
    #[TestWith(['show create procedure p', ShowCreateProcedureStatement::class, 'SHOW CREATE PROCEDURE `p`'])]
    #[TestWith(['show create trigger tr', \SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateTriggerStatement::class, 'SHOW CREATE TRIGGER `tr`'])]
    #[TestWith(['show create table t', \SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateTableStatement::class, 'SHOW CREATE TABLE `t`'])]
    #[TestWith(['show create view v', \SqlSemantics\Model\Statement\Inspection\Definition\ShowCreateViewStatement::class, 'SHOW CREATE VIEW `v`'])]
    #[TestWith(['show create database d', ShowCreateDatabaseStatement::class, 'SHOW CREATE DATABASE `d`'])]
    #[TestWith(['SHOW CREATE SCHEMA IF NOT EXISTS d', ShowCreateDatabaseStatement::class, 'SHOW CREATE DATABASE IF NOT EXISTS `d`'])]
    #[TestWith(['SHOW CREATE USER u', ShowCreateUserStatement::class, 'SHOW CREATE USER \'u\''])]
    #[TestWith(['SHOW FUNCTION CODE f', ShowRoutineCodeStatement::class, 'SHOW FUNCTION CODE `f`'])]
    #[TestWith(['SHOW PROCEDURE STATUS', ShowRoutineStatusStatement::class, 'SHOW PROCEDURE STATUS'])]
    #[TestWith(['SHOW FUNCTION STATUS LIKE \'x\'', ShowRoutineStatusStatement::class, 'SHOW FUNCTION STATUS LIKE \'x\''])]
    public function testCreateRoutesEveryDescribedObject(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)', 'CREATE VIEW v AS SELECT 1')))->bind($sql, strict: false);
        self::assertSame([$class, $expected], [$statement::class, (new \SqlSemantics\SimpleSerializer())->serialize($statement)]);
    }

    public function testRoutineNameAndDatabaseNameReadTheirIdentifiers(): void
    {
        $identifiers = new \SqlSemantics\Ast\Identifiers(Dialect::MySql);
        $parser = new \SqlSemantics\Ast\DialectParser(Dialect::MySql);
        $routine = \SqlSemantics\Ast\Tree::outer($parser->parse('SHOW CREATE FUNCTION d.f'), ['show_create_function_stmt'])[0];
        $database = \SqlSemantics\Ast\Tree::outer($parser->parse('SHOW CREATE DATABASE d'), ['show_create_database_stmt'])[0];
        self::assertSame([['d', 'f'], 'd'], [Definitions::routineName($routine, $identifiers)->parts, Definitions::databaseName($database, $identifiers)]);
    }
}
