<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Program;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Definition\Routine\Body\BlockStatement;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Stored\RoutineCharacteristics;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Definition\MySql\Program\CreateProcedureStatement;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Statement\StatementKind;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateProcedureStatement::class)]
#[Medium]
final class CreateProcedureStatementTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindsTheSameProcedureOnEveryRelease(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build('CREATE TABLE t (n INT)'));
        $statement = $binder->bind("CREATE DEFINER = 'app'@'%' PROCEDURE app.p(IN a INT, OUT b INT) COMMENT 'sum' SQL SECURITY INVOKER BEGIN SELECT SUM(n) INTO b FROM t WHERE n > a; END");
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertSame(StatementKind::Create, $statement->kind);
        self::assertSame(['app', 'p'], $statement->name->parts);
        self::assertInstanceOf(AccountName::class, $statement->definer);
        self::assertFalse($statement->ifNotExists);
        self::assertSame("CREATE DEFINER = 'app'@'%' PROCEDURE `app`.`p`(IN `a` integer, OUT `b` integer) COMMENT 'sum' SQL SECURITY INVOKER BEGIN SELECT sum(`n`) FROM `t` WHERE (`n` > `a`) INTO `b`; END", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testWithOriginPreservesTheDefinition(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
    }

    public function testWithNameReplacesOnlyTheName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $changed = $statement->withName(new QualifiedName(['db', 'q']));
        self::assertSame('CREATE PROCEDURE `db`.`q`() BEGIN END', $changed->toString());
        self::assertSame(['p'], $statement->name->parts);
    }

    public function testWithParametersRevalidatesTheBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT, b INT) SET a = b');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $changed = $statement->withParameters(array_reverse($statement->parameters));
        self::assertSame('CREATE PROCEDURE `p`(IN `b` integer, IN `a` integer) SET `a` = `b`', $changed->toString());
    }

    public function testWithCharacteristicsReplacesAllCharacteristics(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() DETERMINISTIC BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $changed = $statement->withCharacteristics(new RoutineCharacteristics(security: RoutineSecurity::Invoker));
        self::assertSame('CREATE PROCEDURE `p`() SQL SECURITY INVOKER BEGIN END', $changed->toString());
        self::assertTrue($statement->characteristics->deterministic);
    }

    public function testWithBodyReplacesTheBody(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() DO 1');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $changed = $statement->withBody(new BlockStatement('main'));
        self::assertSame('CREATE PROCEDURE `p`() `main` : BEGIN END `main`', $changed->toString());
    }

    public function testWithDefinerRemovesTheDefiner(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE DEFINER = CURRENT_USER PROCEDURE p() BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        self::assertSame('CREATE PROCEDURE `p`() BEGIN END', $statement->withDefiner(null)->toString());
    }

    public function testRejectsRepeatedParameterNames(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p(a INT) BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateProcedureStatement($statement->origin, $statement->name, [...$statement->parameters, ...$statement->parameters], $statement->characteristics, $statement->body);
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateProcedureStatement(new Origin('s0', $statement->source, Dialect::PostgreSql), $statement->name, [], $statement->characteristics, $statement->body);
    }

    public function testRejectsIfNotExistsOnALegacyRelease(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('CREATE PROCEDURE p() BEGIN END');
        self::assertInstanceOf(CreateProcedureStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        new CreateProcedureStatement($statement->origin, $statement->name, [], $statement->characteristics, $statement->body, null, true);
    }
}
