<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Routine\Declaration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterDeclaration;
use SqlSemantics\Model\Definition\Routine\Declaration\ParameterInvariant;
use SqlSemantics\Model\Definition\Routine\Declaration\ResultColumn;
use SqlSemantics\Model\Definition\Routine\ParameterMode;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Definition\Routine\RoutineParameter;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Statement\Definition\PostgreSql\DropFunctionsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ParameterInvariant::class)]
#[Medium]
final class ParameterInvariantTest extends TestCase
{
    public function testParametersAllowsAnOutputAfterADefaultOfAFunction(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $parameters = [new ParameterDeclaration(new RoutineParameter($integer, name: 'a'), Expression::literal(1, Dialect::PostgreSql)), new ParameterDeclaration(new RoutineParameter($integer, ParameterMode::Output, 'a'))];
        ParameterInvariant::parameters($parameters, false);
        $this->expectException(InvalidStructure::class);
        ParameterInvariant::parameters($parameters, true);
    }

    public function testParametersRejectsAnInputAfterVariadic(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FUNCTION f(VARIADIC anyarray, integer)');
        self::assertInstanceOf(DropFunctionsStatement::class, $statement);
        $target = $statement->targets[0];
        self::assertInstanceOf(RoutineBySignature::class, $target);
        ParameterInvariant::parameters([new ParameterDeclaration($target->parameters[0])], false);
        $this->expectException(InvalidStructure::class);
        ParameterInvariant::parameters([new ParameterDeclaration($target->parameters[0]), new ParameterDeclaration($target->parameters[1])], false);
    }

    public function testConflictingAllowsAPureInputAndAPureOutput(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $input = new ParameterDeclaration(new RoutineParameter($integer, name: 'a'));
        $output = new ParameterDeclaration(new RoutineParameter($integer, ParameterMode::Output, 'a'));
        $both = new ParameterDeclaration(new RoutineParameter($integer, ParameterMode::InputOutput, 'a'));
        self::assertSame([false, false, true, true], [ParameterInvariant::conflicting($input, $output), ParameterInvariant::conflicting($output, $input), ParameterInvariant::conflicting($both, $output), ParameterInvariant::conflicting($input, $input)]);
    }

    public function testVariadicRequiresAnArray(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FUNCTION f("any")');
        self::assertInstanceOf(DropFunctionsStatement::class, $statement);
        $target = $statement->targets[0];
        self::assertInstanceOf(RoutineBySignature::class, $target);
        ParameterInvariant::variadic($target->parameters[0]->type);
        $this->expectException(InvalidStructure::class);
        ParameterInvariant::variadic(TypeDescriptor::builtin(Dialect::PostgreSql, 'integer'));
    }

    public function testTableRejectsARepeatedColumnName(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        ParameterInvariant::table([new ParameterDeclaration(new RoutineParameter($integer))], [new ResultColumn('a', $integer)]);
        $this->expectException(InvalidStructure::class);
        ParameterInvariant::table([], [new ResultColumn('a', $integer), new ResultColumn('a', $integer)]);
    }

    public function testTableRejectsAnOutputParameter(): void
    {
        $integer = TypeDescriptor::builtin(Dialect::PostgreSql, 'integer');
        $this->expectException(InvalidStructure::class);
        ParameterInvariant::table([new ParameterDeclaration(new RoutineParameter($integer, ParameterMode::InputOutput))], [new ResultColumn('a', $integer)]);
    }

    #[TestWith(["CREATE PROCEDURE p(OUT b integer, VARIADIC a integer[]) LANGUAGE sql AS ''", 'CREATE PROCEDURE "p"(OUT "b" integer, VARIADIC "a" integer []) LANGUAGE "sql" AS \'\''])]
    #[TestWith(["CREATE FUNCTION f(VARIADIC a integer[], OUT b integer) LANGUAGE sql AS 'select 1'", 'CREATE FUNCTION "f"(VARIADIC "a" integer [], OUT "b" integer) LANGUAGE "sql" AS \'select 1\''])]
    #[TestWith(["CREATE FUNCTION f(a integer, b integer DEFAULT 1) RETURNS integer LANGUAGE sql AS 'select 1'", 'CREATE FUNCTION "f"("a" integer, "b" integer DEFAULT 1) RETURNS integer LANGUAGE "sql" AS \'select 1\''])]
    #[TestWith(["CREATE FUNCTION f(a integer DEFAULT 1, b integer DEFAULT 2) RETURNS integer LANGUAGE sql AS 'select 1'", 'CREATE FUNCTION "f"("a" integer DEFAULT 1, "b" integer DEFAULT 2) RETURNS integer LANGUAGE "sql" AS \'select 1\''])]
    #[TestWith(["CREATE FUNCTION f(IN a integer, OUT a integer) LANGUAGE sql AS 'select 1'", 'CREATE FUNCTION "f"(IN "a" integer, OUT "a" integer) LANGUAGE "sql" AS \'select 1\''])]
    #[TestWith(["CREATE FUNCTION f(integer, integer) RETURNS integer LANGUAGE sql AS 'select 1'", 'CREATE FUNCTION "f"(integer, integer) RETURNS integer LANGUAGE "sql" AS \'select 1\''])]
    #[TestWith(["CREATE FUNCTION f(a integer, b integer) RETURNS integer LANGUAGE sql AS 'select 1'", 'CREATE FUNCTION "f"("a" integer, "b" integer) RETURNS integer LANGUAGE "sql" AS \'select 1\''])]
    public function testParametersAcceptsValidDeclarations(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    #[TestWith(["CREATE PROCEDURE p(VARIADIC a integer[], OUT b integer) LANGUAGE sql AS ''"])]
    #[TestWith(["CREATE FUNCTION f(a integer DEFAULT 1, b integer) RETURNS integer LANGUAGE sql AS 'select 1'"])]
    #[TestWith(["CREATE FUNCTION f(VARIADIC a integer) RETURNS integer LANGUAGE sql AS 'select 1'"])]
    #[TestWith(["CREATE FUNCTION f(a integer, a integer) RETURNS integer LANGUAGE sql AS 'select 1'"])]
    public function testParametersRejectsInvalidDeclarations(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }
}
