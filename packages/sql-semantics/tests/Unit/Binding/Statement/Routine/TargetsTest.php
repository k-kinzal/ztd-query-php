<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Routine;
use SqlSemantics\Model\Statement\Definition\PostgreSql;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Routine\Targets::class)]
#[Medium]
final class TargetsTest extends TestCase
{
    public function testRoutineKeepsUnspecifiedArgumentsDistinctFromAnEmptyList(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FUNCTION f, f()');
        self::assertInstanceOf(PostgreSql\DropFunctionsStatement::class, $statement);
        self::assertInstanceOf(Routine\RoutineByName::class, $statement->targets[0]);
        self::assertInstanceOf(Routine\RoutineBySignature::class, $statement->targets[1]);
        self::assertSame([], $statement->targets[1]->parameters);
    }

    public function testNameRejectsSubscriptsInTheRoutineIdentity(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('without subscripts or wildcards');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP FUNCTION f[1]');
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['DROP FUNCTION x.a.b.f()'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DROP PROCEDURE x.a.b.p'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DROP AGGREGATE x.a.b.g(integer)'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['GRANT EXECUTE ON FUNCTION x.a.b.f() TO r'])]
    public function testNameRejectsMoreThanCatalogSchemaAndRoutine(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage(\SqlSemantics\Model\Validation\InputViolation::CatalogObjectName->message());
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

    public function testAggregateClassifiesZeroOrdinaryAndOrderedSetSignatures(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP AGGREGATE f(*), g(integer), h(ORDER BY integer), i(integer ORDER BY text)');
        self::assertInstanceOf(PostgreSql\DropAggregatesStatement::class, $statement);
        self::assertInstanceOf(Routine\ZeroArgumentAggregate::class, $statement->targets[0]);
        self::assertInstanceOf(Routine\OrdinaryAggregate::class, $statement->targets[1]);
        self::assertCount(1, $statement->targets[1]->parameters);
        self::assertInstanceOf(Routine\OrderedSetAggregate::class, $statement->targets[2]);
        self::assertSame([], $statement->targets[2]->direct);
        self::assertCount(1, $statement->targets[2]->ordered);
        self::assertInstanceOf(Routine\OrderedSetAggregate::class, $statement->targets[3]);
        self::assertCount(1, $statement->targets[3]->direct);
        self::assertCount(1, $statement->targets[3]->ordered);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testAggregateRetainsMatchingVariadicInputsInBothPositions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('DROP AGGREGATE f(VARIADIC d integer[] ORDER BY VARIADIC o integer[])');
        self::assertInstanceOf(PostgreSql\DropAggregatesStatement::class, $statement);
        self::assertInstanceOf(Routine\OrderedSetAggregate::class, $statement->targets[0]);
        self::assertSame('d', $statement->targets[0]->direct[0]->name);
        self::assertSame('o', $statement->targets[0]->ordered[0]->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testAggregateRejectsAnInconsistentVariadicSignature(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('same declared type');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP AGGREGATE f(VARIADIC integer[] ORDER BY VARIADIC text[])');
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['drop function s.f(int, text)', 'DROP FUNCTION "s"."f"(integer, text)'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['drop function s.f', 'DROP FUNCTION "s"."f"'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['drop aggregate a(int order by int)', 'DROP AGGREGATE "a"(integer ORDER BY integer)'])]
    public function testRoutineAndAggregateKeepQualifiedNamesAndSignatures(string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql)->toString());
    }

    public function testNameReadsTheQualifiedComponents(): void
    {
        $context = new \SqlSemantics\Binding\Query\QueryContext(new \SqlSemantics\Binding\TableResolver((new SchemaBuilder(Dialect::PostgreSql))->build(), new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql), 'public'));
        $name = (new \SqlSemantics\Ast\DialectParser(Dialect::PostgreSql))->parse('DROP FUNCTION s.f(int)')->find('func_name')[0];
        self::assertSame(['s', 'f'], \SqlSemantics\Binding\Statement\Routine\Targets::name($name, $context)->parts);
    }
}
