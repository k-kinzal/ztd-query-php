<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\ParameterStyle;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Lowering\Form;
use SqlSemantics\Lowering\Leaves;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Lowering;
use SqlSemantics\Platform\MySql\Lowering\Routine\DefinitionRule;
use SqlSemantics\Platform\MySql\Platform;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\RoutineComment;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateLoadableFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\DropProgram;
use SqlSemantics\Platform\MySql\Statement\Routine\ExternalBody;
use SqlSemantics\Platform\MySql\Statement\Routine\LoadableResult;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;

#[CoversClass(DefinitionRule::class)]
#[Medium]
final class DefinitionRuleTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRoutineLowersAProcedure(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 8.1' => ['mysql-8.1.0'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerRoutineLowersAProcedure')]
    public function testRoutineLowersAProcedure(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze("create definer = 'root'@'%' procedure db.p(in a int, out b int, inout c int) comment 'x' select a");
        $statement = $create->statement;
        self::assertInstanceOf(CreateProcedure::class, $statement);
        self::assertSame('p', $statement->name->name->value);
        self::assertSame('db', $statement->name->schema?->value);
        self::assertCount(3, $statement->parameters->parameters);
        self::assertCount(1, $statement->characteristics);
        self::assertInstanceOf(RoutineComment::class, $statement->characteristics[0]);
        self::assertInstanceOf(Select::class, $statement->body);
        self::assertNotNull($statement->definer);
        self::assertFalse($statement->ifNotExists);
        self::assertSame("CREATE DEFINER = root@`%` PROCEDURE db.p(IN a INT, OUT b INT, INOUT c INT) COMMENT 'x' SELECT a", $create->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRoutineLowersAFunctionWithItsReturnCollation(): iterable
    {
        yield 'mysql 5.6' => ['mysql-5.6.51'];
        yield 'mysql 5.7' => ['mysql-5.7.44'];
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerRoutineLowersAFunctionWithItsReturnCollation')]
    public function testRoutineLowersAFunctionWithItsReturnCollation(string $release): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze('create function f(a int) returns varchar(5) collate utf8mb4_bin deterministic return a');
        $statement = $create->statement;
        self::assertInstanceOf(CreateFunction::class, $statement);
        self::assertSame('f', $statement->name->name->value);
        self::assertCount(1, $statement->parameters->parameters);
        self::assertSame('utf8mb4_bin', $statement->collation?->name?->value);
        self::assertInstanceOf(ReturnStatement::class, $statement->body);
        self::assertNull($statement->definer);
        self::assertFalse($statement->ifNotExists);
        self::assertSame('CREATE FUNCTION f(a INT) RETURNS VARCHAR(5) COLLATE utf8mb4_bin DETERMINISTIC RETURN a', $create->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerRoutineReadsIfNotExists(): iterable
    {
        yield 'mysql 8.0' => ['mysql-8.0.44'];
        yield 'mysql 8.4' => ['mysql-8.4.7'];
        yield 'mysql 9.1' => ['mysql-9.1.0'];
    }

    #[DataProvider('providerRoutineReadsIfNotExists')]
    public function testRoutineReadsIfNotExists(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        $procedure = $semantics->analyze('create procedure if not exists p() select 1');
        self::assertInstanceOf(CreateProcedure::class, $procedure->statement);
        self::assertTrue($procedure->statement->ifNotExists);
        self::assertSame('CREATE PROCEDURE IF NOT EXISTS p() SELECT 1', $procedure->toString());

        $function = $semantics->analyze('create function if not exists db.f() returns int return 1');
        self::assertInstanceOf(CreateFunction::class, $function->statement);
        self::assertTrue($function->statement->ifNotExists);
        self::assertNull($function->statement->collation);
        self::assertSame('CREATE FUNCTION IF NOT EXISTS db.f() RETURNS INT RETURN 1', $function->toString());
    }

    public function testRoutineLowersAStoredRoutineBody(): void
    {
        $semantics = new Semantics(Dialect::MySql, 'mysql-8.1.0');
        $function = $semantics->analyze("create function f() returns int language javascript as 'return 1'");
        self::assertInstanceOf(CreateFunction::class, $function->statement);
        self::assertInstanceOf(ExternalBody::class, $function->statement->body);
        self::assertSame("CREATE FUNCTION f() RETURNS INT LANGUAGE javascript AS 'return 1'", $function->toString());

        $procedure = $semantics->analyze('create procedure p() language javascript as $$ return 1 $$');
        self::assertInstanceOf(CreateProcedure::class, $procedure->statement);
        self::assertInstanceOf(ExternalBody::class, $procedure->statement->body);
        self::assertSame('CREATE PROCEDURE p() LANGUAGE javascript AS $$ return 1 $$', $procedure->toString());
    }

    public function testRoutineRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new DefinitionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->routine(new Node('sp_name', 1, []), null);
    }

    /**
     * @return iterable<string, array{string, string, LoadableResult, bool, bool, string}>
     */
    public static function providerLoadableLowersEveryLayoutAndResult(): iterable
    {
        yield 'aggregate on 5.6' => ['mysql-5.6.51', "create aggregate function u returns real soname 'u.so'", LoadableResult::Real, true, false, "CREATE AGGREGATE FUNCTION u RETURNS REAL SONAME 'u.so'"];
        yield 'plain on 5.6' => ['mysql-5.6.51', "create function u returns int soname 'u.so'", LoadableResult::Integer, false, false, "CREATE FUNCTION u RETURNS INTEGER SONAME 'u.so'"];
        yield 'aggregate on 5.7' => ['mysql-5.7.44', "create aggregate function u returns string soname 'u.so'", LoadableResult::String, true, false, "CREATE AGGREGATE FUNCTION u RETURNS STRING SONAME 'u.so'"];
        yield 'plain on 5.7' => ['mysql-5.7.44', "create function u returns decimal soname 'u.so'", LoadableResult::Decimal, false, false, "CREATE FUNCTION u RETURNS DECIMAL SONAME 'u.so'"];
        yield 'aggregate if not exists on 8.0' => ['mysql-8.0.44', "create aggregate function if not exists u returns integer soname 'u.so'", LoadableResult::Integer, true, true, "CREATE AGGREGATE FUNCTION IF NOT EXISTS u RETURNS INTEGER SONAME 'u.so'"];
        yield 'plain on 8.0' => ['mysql-8.0.44', "create function u returns real soname 'u.so'", LoadableResult::Real, false, false, "CREATE FUNCTION u RETURNS REAL SONAME 'u.so'"];
        yield 'plain if not exists on 9.1' => ['mysql-9.1.0', "create function if not exists u returns string soname 'u.so'", LoadableResult::String, false, true, "CREATE FUNCTION IF NOT EXISTS u RETURNS STRING SONAME 'u.so'"];
    }

    #[DataProvider('providerLoadableLowersEveryLayoutAndResult')]
    public function testLoadableLowersEveryLayoutAndResult(string $release, string $sql, LoadableResult $result, bool $aggregate, bool $ifNotExists, string $expected): void
    {
        $create = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $create->statement;
        self::assertInstanceOf(CreateLoadableFunction::class, $statement);
        self::assertSame('u', $statement->name->value);
        self::assertSame($result, $statement->returns);
        self::assertSame('u.so', $statement->library->value);
        self::assertSame($aggregate, $statement->aggregate);
        self::assertSame($ifNotExists, $statement->ifNotExists);
        self::assertSame($expected, $create->toString());
    }

    public function testLoadableRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new DefinitionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: sp_name: ident');

        $rule->loadable(new Node('sp_name', 1, []));
    }

    /**
     * @return iterable<string, array{string, string, ProgramKind, string}>
     */
    public static function providerAlterLowersProceduresAndFunctions(): iterable
    {
        yield 'procedure on 5.6' => ['mysql-5.6.51', "alter procedure db.p comment 'x'", ProgramKind::Procedure, "ALTER PROCEDURE db.p COMMENT 'x'"];
        yield 'function on 5.7' => ['mysql-5.7.44', 'alter function db.p no sql', ProgramKind::Function, 'ALTER FUNCTION db.p NO SQL'];
        yield 'procedure on 8.0' => ['mysql-8.0.44', 'alter procedure db.p sql security invoker', ProgramKind::Procedure, 'ALTER PROCEDURE db.p SQL SECURITY INVOKER'];
        yield 'function on 9.1' => ['mysql-9.1.0', 'alter function db.p language sql', ProgramKind::Function, 'ALTER FUNCTION db.p LANGUAGE SQL'];
    }

    #[DataProvider('providerAlterLowersProceduresAndFunctions')]
    public function testAlterLowersProceduresAndFunctions(string $release, string $sql, ProgramKind $kind, string $expected): void
    {
        $alter = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $alter->statement;
        self::assertInstanceOf(AlterRoutine::class, $statement);
        self::assertSame($kind, $statement->kind);
        self::assertSame('p', $statement->name->name->value);
        self::assertSame('db', $statement->name->schema?->value);
        self::assertCount(1, $statement->characteristics);
        self::assertSame($expected, $alter->toString());
    }

    public function testAlterRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new DefinitionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: drop_view_stmt: DROP VIEW_SYM if_exists table_list opt_restrict');

        $rule->alter(new Form(new Node('drop_view_stmt', 0, []), 'drop_view_stmt: DROP VIEW_SYM if_exists table_list opt_restrict'));
    }

    /**
     * @return iterable<string, array{string, string, ProgramKind, string|null, bool, string}>
     */
    public static function providerDropLowersEveryKindOfProgram(): iterable
    {
        yield 'procedure on 5.6' => ['mysql-5.6.51', 'drop procedure if exists db.x', ProgramKind::Procedure, 'db', true, 'DROP PROCEDURE IF EXISTS db.x'];
        yield 'qualified function on 5.6' => ['mysql-5.6.51', 'drop function db.x', ProgramKind::Function, 'db', false, 'DROP FUNCTION db.x'];
        yield 'function on 5.6' => ['mysql-5.6.51', 'drop function if exists x', ProgramKind::Function, null, true, 'DROP FUNCTION IF EXISTS x'];
        yield 'trigger on 5.7' => ['mysql-5.7.44', 'drop trigger db.x', ProgramKind::Trigger, 'db', false, 'DROP TRIGGER db.x'];
        yield 'event on 5.7' => ['mysql-5.7.44', 'drop event if exists x', ProgramKind::Event, null, true, 'DROP EVENT IF EXISTS x'];
        yield 'procedure on 8.0' => ['mysql-8.0.44', 'drop procedure x', ProgramKind::Procedure, null, false, 'DROP PROCEDURE x'];
        yield 'qualified function on 9.1' => ['mysql-9.1.0', 'drop function if exists db.x', ProgramKind::Function, 'db', true, 'DROP FUNCTION IF EXISTS db.x'];
        yield 'function on 9.1' => ['mysql-9.1.0', 'drop function x', ProgramKind::Function, null, false, 'DROP FUNCTION x'];
        yield 'trigger on 9.1' => ['mysql-9.1.0', 'drop trigger if exists x', ProgramKind::Trigger, null, true, 'DROP TRIGGER IF EXISTS x'];
        yield 'event on 9.1' => ['mysql-9.1.0', 'drop event db.x', ProgramKind::Event, 'db', false, 'DROP EVENT db.x'];
    }

    #[DataProvider('providerDropLowersEveryKindOfProgram')]
    public function testDropLowersEveryKindOfProgram(string $release, string $sql, ProgramKind $kind, ?string $schema, bool $ifExists, string $expected): void
    {
        $drop = (new Semantics(Dialect::MySql, $release))->analyze($sql);
        $statement = $drop->statement;
        self::assertInstanceOf(DropProgram::class, $statement);
        self::assertSame($kind, $statement->kind);
        self::assertSame('x', $statement->name->name->value);
        self::assertSame($schema, $statement->name->schema?->value);
        self::assertSame($ifExists, $statement->ifExists);
        self::assertSame($expected, $drop->toString());
    }

    public function testDropRefusesAnotherProduction(): void
    {
        $platform = new Platform();
        $profile = $platform->profile(null, null, ParameterStyle::Native);
        $rule = new DefinitionRule(new Lowering($platform->productions($profile), new Leaves(), $profile));

        $this->expectExceptionMessage('No semantic rule is implemented for: drop_view_stmt: DROP VIEW_SYM if_exists table_list opt_restrict');

        $rule->drop(new Form(new Node('drop_view_stmt', 0, []), 'drop_view_stmt: DROP VIEW_SYM if_exists table_list opt_restrict'));
    }
}
