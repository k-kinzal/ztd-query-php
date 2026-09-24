<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Definition\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\CurrentAccount;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Statement\Definition\MySql\Program\AlterEventStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateEventStatement;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateFunctionStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Definition\Program\StoredPrograms;

#[CoversClass(StoredPrograms::class)]
#[Medium]
final class StoredProgramsTest extends TestCase
{
    public function testWriteReturnsNullForOtherStatements(): void
    {
        self::assertNull(StoredPrograms::write((new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT 1')));
    }

    public function testRoutineWritesParametersReturnsAndCharacteristics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION IF NOT EXISTS f(a CHAR(1) COLLATE utf8mb4_bin) RETURNS INT COMMENT 'c' READS SQL DATA RETURN 1");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame("CREATE FUNCTION IF NOT EXISTS `f`(`a` char(1) COLLATE `utf8mb4_bin`) RETURNS integer COMMENT 'c' READS SQL DATA RETURN 1", StoredPrograms::routine($statement)->toString());
    }

    public function testHeadWritesDefinerAndIfNotExists(): void
    {
        self::assertSame('CREATE DEFINER = CURRENT_USER EVENT IF NOT EXISTS', (new \SqlSemantics\Model\Sql\Tree('head', StoredPrograms::head(CurrentAccount::Authenticated, 'EVENT', true)))->toString());
    }

    public function testDefinerIsEmptyWithoutAnAccount(): void
    {
        self::assertSame([], StoredPrograms::definer(null));
    }

    public function testCharacteristicsOmitsServerDefaults(): void
    {
        self::assertSame([], StoredPrograms::characteristics(new RoutineCharacteristics()));
        self::assertCount(2, StoredPrograms::characteristics(new RoutineCharacteristics(false, SqlDataAccess::None, RoutineSecurity::Invoker)));
    }

    public function testCommentKeepsTheLiteralSpelling(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT "x"');
        self::assertInstanceOf(\SqlSemantics\Model\BoundQuery::class, $statement);
        $literal = $statement->resultColumns()[0]->expression;
        self::assertInstanceOf(Literal::class, $literal);
        self::assertSame('COMMENT "x"', (new \SqlSemantics\Model\Sql\Tree('comment', StoredPrograms::comment($literal)))->toString());
        self::assertSame([], StoredPrograms::comment(null));
    }

    public function testScheduleWritesTheWindow(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 DAY STARTS CURRENT_TIMESTAMP ENDS CURRENT_TIMESTAMP + INTERVAL 1 WEEK DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertSame('ON SCHEDULE EVERY 1 DAY STARTS(CURRENT_TIMESTAMP) ENDS(DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 WEEK))', StoredPrograms::schedule($statement->schedule)->toString());
    }

    public function testOperandParenthesizesOnlyNonLiterals(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE EVERY 1 + 1 DAY DO DO 1');
        self::assertInstanceOf(CreateEventStatement::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Definition\Routine\Stored\RecurringSchedule::class, $statement->schedule);
        self::assertSame('((1 + 1))', StoredPrograms::operand($statement->schedule->every)->toString());
    }

    public function testAlterEventWritesOnlyRequestedChanges(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER EVENT e RENAME TO f');
        self::assertInstanceOf(AlterEventStatement::class, $statement);
        self::assertSame('ALTER EVENT `e` RENAME TO `f`', StoredPrograms::alterEvent($statement)->toString());
    }

    public function testWriteSpellsTheTriggerWithDefinerGuardAndOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind("CREATE DEFINER = 'u'@'h' TRIGGER IF NOT EXISTS tr BEFORE INSERT ON t FOR EACH ROW FOLLOWS other SET @x = 1");
        self::assertSame("CREATE DEFINER = 'u'@'h' TRIGGER IF NOT EXISTS `tr` BEFORE INSERT ON `t` FOR EACH ROW FOLLOWS `other` SET @`x` = 1", StoredPrograms::write($statement)?->toString());
    }

    public function testWriteSpellsTheTriggerTimingAndEventWithoutAnOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER tr AFTER DELETE ON t FOR EACH ROW BEGIN END');
        self::assertSame('CREATE TRIGGER `tr` AFTER DELETE ON `t` FOR EACH ROW BEGIN END', StoredPrograms::write($statement)?->toString());
    }

    public function testWriteSpellsTheTriggerPrecedingAnotherTrigger(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('CREATE TRIGGER tr BEFORE UPDATE ON t FOR EACH ROW PRECEDES o SET @x = 1');
        self::assertSame('CREATE TRIGGER `tr` BEFORE UPDATE ON `t` FOR EACH ROW PRECEDES `o` SET @`x` = 1', StoredPrograms::write($statement)?->toString());
    }

    public function testWriteSpellsTheEventWithEveryNonDefaultClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE DEFINER = CURRENT_USER EVENT IF NOT EXISTS e ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE COMMENT 'c' DO SELECT 1");
        self::assertSame("CREATE DEFINER = CURRENT_USER EVENT IF NOT EXISTS `e` ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION PRESERVE DISABLE COMMENT 'c' DO SELECT 1", StoredPrograms::write($statement)?->toString());
    }

    public function testWriteOmitsTheEventDefaults(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP ON COMPLETION NOT PRESERVE ENABLE DO DO 1');
        self::assertSame('CREATE EVENT `e` ON SCHEDULE AT(CURRENT_TIMESTAMP) DO DO 1', StoredPrograms::write($statement)?->toString());
    }

    public function testWriteSpellsTheReplicaDisabledEvent(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE EVENT e ON SCHEDULE AT CURRENT_TIMESTAMP DISABLE ON SLAVE DO DO 1');
        self::assertSame('CREATE EVENT `e` ON SCHEDULE AT(CURRENT_TIMESTAMP) DISABLE ON SLAVE DO DO 1', StoredPrograms::write($statement)?->toString());
    }

    public function testWriteSpellsEveryAlterEventChange(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("ALTER DEFINER = CURRENT_USER EVENT e ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION NOT PRESERVE RENAME TO f ENABLE COMMENT 'x' DO DO 2");
        self::assertSame("ALTER DEFINER = CURRENT_USER EVENT `e` ON SCHEDULE AT '2030-01-01 00:00:00' ON COMPLETION NOT PRESERVE RENAME TO `f` ENABLE COMMENT 'x' DO DO 2", StoredPrograms::write($statement)?->toString());
    }

    public function testWriteSpellsTheProcedureModesAndCharacteristics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(IN a INT, OUT b TEXT, INOUT c INT) DETERMINISTIC NO SQL SQL SECURITY INVOKER BEGIN END');
        self::assertSame('CREATE PROCEDURE `p`(IN `a` integer, OUT `b` text, INOUT `c` integer) DETERMINISTIC NO SQL SQL SECURITY INVOKER BEGIN END', StoredPrograms::write($statement)?->toString());
    }

    public function testWriteSpellsTheFunctionWithModifiesSqlData(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE FUNCTION g(a INT, b INT) RETURNS INT MODIFIES SQL DATA RETURN a');
        self::assertSame('CREATE FUNCTION `g`(`a` integer, `b` integer) RETURNS integer MODIFIES SQL DATA RETURN `a`', StoredPrograms::write($statement)?->toString());
    }

    public function testRoutineEscapesExternalCode(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE FUNCTION f() RETURNS INT LANGUAGE JAVASCRIPT AS 'return \"it''s\\\\x\"'");
        self::assertInstanceOf(CreateFunctionStatement::class, $statement);
        self::assertSame('CREATE FUNCTION `f`() RETURNS integer LANGUAGE `JAVASCRIPT` AS \'return "it\'\'s\\\\x"\'', StoredPrograms::routine($statement)->toString());
    }
}
