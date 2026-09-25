<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Characteristics\RoutineSecurity;
use SqlSemantics\Model\Definition\Routine\Characteristics\SqlDataAccess;
use SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Routine\AlterRoutines::class)]
#[Medium]
final class AlterRoutinesTest extends TestCase
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
    public function testBindDistinguishesFunctionAndProcedureTargetsAcrossReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $function = $binder->bind('ALTER FUNCTION app.f READS SQL DATA');
        $procedure = $binder->bind('ALTER PROCEDURE app.p SQL SECURITY INVOKER');
        self::assertInstanceOf(AlterFunctionStatement::class, $function);
        self::assertInstanceOf(AlterProcedureStatement::class, $procedure);
        self::assertSame(['app', 'f'], $function->name->parts);
        self::assertSame(['app', 'p'], $procedure->name->parts);
        self::assertSame(SqlDataAccess::Reads, $function->changes->dataAccess);
        self::assertSame(RoutineSecurity::Invoker, $procedure->changes->security);
    }

    public function testBindAllowsAnExplicitRequestWithAllCharacteristicsUnchanged(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER FUNCTION f');
        self::assertInstanceOf(AlterFunctionStatement::class, $statement);
        self::assertNull($statement->changes->language);
        self::assertNull($statement->changes->dataAccess);
        self::assertNull($statement->changes->security);
        self::assertNull($statement->changes->comment);
    }

    #[TestWith(['ALTER FUNCTION ``'])]
    #[TestWith(['ALTER PROCEDURE app.``'])]
    public function testBindRejectsAnEmptyRoutineIdentity(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    #[TestWith(['alter function f comment \'x\'', 'ALTER FUNCTION `f` COMMENT \'x\''])]
    #[TestWith(['alter procedure p sql security invoker', 'ALTER PROCEDURE `p` SQL SECURITY INVOKER'])]
    public function testBindReadsLowerCaseAlterations(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql)));
    }
}
