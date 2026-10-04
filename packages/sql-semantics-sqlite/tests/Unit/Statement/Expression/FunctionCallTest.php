<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\Frame;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBound;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameBoundKind;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\FrameUnit;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\WrongArgumentCount;
use SqlSemantics\Platform\Sqlite\Statement\Query\ResultColumn;
use SqlSemantics\Platform\Sqlite\Statement\Query\Select;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Platform\Sqlite\Statement\Type\Storage;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Missing\UndeclaredRoutine;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(FunctionCall::class)]
#[Medium]
final class FunctionCallTest extends TestCase
{
    public function testDeriveScalarReadsThePartsOfAnAggregateCall(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT count(DISTINCT a ORDER BY b) FILTER (WHERE a > 0), count(*) FROM t', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[0]->expression);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[1]->expression);
        $call = $statement->columns[0]->expression;
        self::assertSame('count', $call->name->value);
        self::assertSame(SetQuantifier::Distinct, $call->quantifier);
        self::assertCount(1, $call->arguments);
        self::assertCount(1, $call->order);
        self::assertNotNull($call->filter);
        self::assertFalse($call->star);
        self::assertNull($call->over);
        self::assertTrue($statement->columns[1]->expression->star);
        self::assertEquals(new Known(Storage::Integer), $operation->field(0)->type);
        self::assertSame(Nullability::NotNull, $operation->field(0)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarGivesTheDocumentedResultOfBuiltInFunctions(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT abs(a), length(b), total(a), coalesce(b, 1), upper(b), typeof(b), max(a), sum(a) FROM t', [$create]);

        self::assertEquals(new Choice([Storage::Integer, Storage::Real]), $operation->field(0)->type);
        self::assertSame(Nullability::Nullable, $operation->field(0)->nullability);
        self::assertEquals(new Known(Storage::Integer), $operation->field(1)->type);
        self::assertSame(Nullability::Nullable, $operation->field(1)->nullability);
        self::assertEquals(new Known(Storage::Real), $operation->field(2)->type);
        self::assertSame(Nullability::NotNull, $operation->field(2)->nullability);
        self::assertEquals(new Choice([Storage::Integer, Storage::Text, Storage::Blob]), $operation->field(3)->type);
        self::assertSame(Nullability::NotNull, $operation->field(3)->nullability);
        self::assertEquals(new Known(Storage::Text), $operation->field(4)->type);
        self::assertSame(Nullability::NotNull, $operation->field(5)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(6)->nullability);
        self::assertEquals(new Choice([Storage::Integer, Storage::Real]), $operation->field(7)->type);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsAWrongArgumentCount(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT abs(1, 2), abs()', []);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[0]);
        $fact = $operation->facts->scalar($statement->columns[0]->expression);
        self::assertInstanceOf(Invalid::class, $fact->type);
        self::assertEquals(new WrongArgumentCount(new Name('abs'), 2), $fact->type->cause);
        self::assertSame(Nullability::Dependent, $fact->nullability);
        self::assertEquals([new WrongArgumentCount(new Name('abs'), 2), new WrongArgumentCount(new Name('abs'), 0)], $operation->facts->diagnostics);
        self::assertSame('wrong number of arguments to function abs()', $operation->facts->diagnostics[1]->message());
    }

    public function testDeriveScalarDependsOnAnUndeclaredRoutine(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT foo(1)', []);

        self::assertEquals(new Dependent([new UndeclaredRoutine(new QualifiedName(new Name('foo')))]), $operation->field(0)->type);
        self::assertSame(Nullability::Dependent, $operation->field(0)->nullability);
        self::assertSame([], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsAWindowFunctionWithoutOver(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT row_number()', []);

        self::assertEquals([new CallMisuse(CallMisuseRule::WindowWithoutOver, new Name('row_number'))], $operation->facts->diagnostics);
        self::assertSame('misuse of window function row_number()', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarReportsAScalarFunctionUsedAsAWindow(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT abs(1) OVER ()', []);

        self::assertEquals([new CallMisuse(CallMisuseRule::ScalarAsWindow, new Name('abs'))], $operation->facts->diagnostics);
        self::assertSame('abs() may not be used as a window function', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarReportsAFilterOnAScalarFunction(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT abs(1) FILTER (WHERE 1)', []);

        self::assertEquals([new CallMisuse(CallMisuseRule::FilterWithoutAggregate, new Name('abs'))], $operation->facts->diagnostics);
        self::assertSame('FILTER may not be used with non-aggregate abs()', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarReportsAFilterOnAWindowOnlyFunction(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT rank() FILTER (WHERE 1) OVER () FROM t', [$create]);

        self::assertEquals([new CallMisuse(CallMisuseRule::FilterOnWindowOnly, new Name('rank'))], $operation->facts->diagnostics);
        self::assertSame('FILTER clause may only be used with aggregate window functions', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarReportsAnOrderingInsideAScalarCall(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze('SELECT abs(1 ORDER BY 1)', []);

        self::assertEquals([new CallMisuse(CallMisuseRule::OrderByWithoutAggregate, new Name('abs'))], $operation->facts->diagnostics);
    }

    public function testDeriveScalarReportsDistinctInAWindow(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT sum(DISTINCT a) OVER () FROM t', [$create]);

        self::assertEquals([new CallMisuse(CallMisuseRule::DistinctInWindow, new Name('sum'))], $operation->facts->diagnostics);
        self::assertSame('DISTINCT is not supported for window functions', $operation->facts->diagnostics[0]->message());
    }

    public function testDeriveScalarReportsDistinctWithSeveralArguments(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze("SELECT group_concat(DISTINCT a, ',') FROM t", [$create]);

        self::assertEquals([new CallMisuse(CallMisuseRule::DistinctArguments, new Name('group_concat'))], $operation->facts->diagnostics);
        self::assertSame('DISTINCT aggregates must have exactly one argument', $operation->facts->diagnostics[0]->message());
        self::assertEquals(new Known(Storage::Text), $operation->field(0)->type);
    }

    public function testDeriveScalarAcceptsAggregatesUsedAsWindowsAndNamedWindows(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $create = $semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)');
        $operation = $semantics->analyze('SELECT sum(a) FILTER (WHERE a > 0) OVER (PARTITION BY b), rank() OVER w, lag(a, 1, 0) OVER (ORDER BY a) FROM t WINDOW w AS (ORDER BY a)', [$create]);
        $statement = $operation->statement;

        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(ResultColumn::class, $statement->columns[1]);
        self::assertInstanceOf(FunctionCall::class, $statement->columns[1]->expression);
        self::assertInstanceOf(Name::class, $statement->columns[1]->expression->over);
        self::assertSame('w', $statement->columns[1]->expression->over->value);
        self::assertSame([], $operation->facts->diagnostics);
        self::assertEquals(new Known(Storage::Integer), $operation->field(1)->type);
        self::assertSame(Nullability::NotNull, $operation->field(1)->nullability);
        self::assertSame(Nullability::Nullable, $operation->field(2)->nullability);
    }

    public function testRenderWritesEveryPartInOrder(): void
    {
        $operation = (new Semantics(Dialect::Sqlite))->analyze("select count(*), count(distinct a order by b desc) filter (where a > 0), group_concat(b, ',') over (partition by a), rank() over w, foo() from t window w as ()");

        self::assertSame("SELECT count(*), count(DISTINCT a ORDER BY b DESC) FILTER (WHERE a > 0), group_concat(b, ',') OVER (PARTITION BY a), rank() OVER w, foo() FROM t WINDOW w AS ()", $operation->toString());
    }

    public function testRenderWritesANewlyBuiltCall(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $frame = new Frame(FrameUnit::Rows, new FrameBound(FrameBoundKind::UnboundedPreceding), new FrameBound(FrameBoundKind::CurrentRow));
        $operation = new Operation($semantics->context(), new Select([new ResultColumn(new FunctionCall(new Name('sum'), [new IntegerLiteral('1')], false, null, [], null, new WindowSpec(null, [], [], $frame))), new ResultColumn(new FunctionCall(new Name('count'), [], true))]));

        self::assertSame('SELECT sum(1) OVER (ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW), count(*)', $operation->toString());
        self::assertSame([], $operation->facts->diagnostics);
    }
}
