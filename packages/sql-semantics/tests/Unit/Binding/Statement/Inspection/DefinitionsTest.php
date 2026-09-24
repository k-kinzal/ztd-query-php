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
        self::assertSame("SHOW PROCEDURE STATUS WHERE (`Db` = 'app')", $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
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
        self::assertSame('SHOW CREATE DATABASE IF NOT EXISTS `my db`', $database->toString());
    }

    public function testRoutineNameUnquotesEachPart(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW FUNCTION CODE `a``b`.`c d`');
        self::assertInstanceOf(ShowRoutineCodeStatement::class, $statement);
        self::assertSame(['a`b', 'c d'], $statement->name->parts);
        self::assertSame('SHOW FUNCTION CODE `a``b`.`c d`', $statement->toString());
    }

    public function testDatabaseNameUnquotesTheIdentifier(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SHOW CREATE DATABASE `a``b`');
        self::assertInstanceOf(ShowCreateDatabaseStatement::class, $statement);
        self::assertSame('a`b', $statement->database);
        self::assertSame('SHOW CREATE DATABASE `a``b`', $statement->toString());
    }
}
