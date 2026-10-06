<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Typing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Platform\Sqlite\Rules\Typing\CallChecks;
use SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuse;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\CallMisuseRule;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\WrongArgumentCount;

#[CoversClass(CallChecks::class)]
#[Medium]
final class CallChecksTest extends TestCase
{
    public function testReportRecordsAWindowFunctionCalledWithoutOver(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT row_number()');

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[0]);
        self::assertSame(CallMisuseRule::WindowWithoutOver, $query->facts->diagnostics[0]->rule);
        self::assertSame('row_number', $query->facts->diagnostics[0]->function->value);
        self::assertSame('misuse of window function row_number()', $query->facts->diagnostics[0]->message());
    }

    public function testReportRecordsAScalarFunctionCalledAsAWindowOrWithFilter(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT abs(1) OVER (), abs(1) FILTER (WHERE 1)');
        $messages = array_map(static fn (object $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics);

        self::assertSame(['abs() may not be used as a window function', 'FILTER may not be used with non-aggregate abs()'], $messages);
        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[0]);
        self::assertSame(CallMisuseRule::ScalarAsWindow, $query->facts->diagnostics[0]->rule);
        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[1]);
        self::assertSame(CallMisuseRule::FilterWithoutAggregate, $query->facts->diagnostics[1]->rule);
    }

    public function testReportRecordsAFilterOnAWindowOnlyFunction(): void
    {
        $query = (new Semantics(Dialect::Sqlite))->analyze('SELECT row_number() FILTER (WHERE 1) OVER ()');

        self::assertCount(1, $query->facts->diagnostics);
        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[0]);
        self::assertSame(CallMisuseRule::FilterOnWindowOnly, $query->facts->diagnostics[0]->rule);
        self::assertSame('FILTER clause may only be used with aggregate window functions', $query->facts->diagnostics[0]->message());
    }

    public function testReportRecordsAnArgumentOrderingOutsideAnAggregate(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT abs(a ORDER BY b), count(a ORDER BY b) OVER (), group_concat(a ORDER BY b) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $messages = array_map(static fn (object $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics);

        self::assertSame(['ORDER BY may not be used with non-aggregate abs()', 'ORDER BY may not be used with non-aggregate count()'], $messages);
        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[1]);
        self::assertSame(CallMisuseRule::OrderByWithoutAggregate, $query->facts->diagnostics[1]->rule);
    }

    public function testReportRecordsDistinctInAWindowAndDistinctWithSeveralArguments(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze("SELECT sum(DISTINCT a) OVER (), group_concat(DISTINCT a, ',') FROM t", [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);
        $messages = array_map(static fn (object $diagnostic): string => $diagnostic->message(), $query->facts->diagnostics);

        self::assertSame(['DISTINCT is not supported for window functions', 'DISTINCT aggregates must have exactly one argument'], $messages);
        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[0]);
        self::assertSame(CallMisuseRule::DistinctInWindow, $query->facts->diagnostics[0]->rule);
        self::assertInstanceOf(CallMisuse::class, $query->facts->diagnostics[1]);
        self::assertSame(CallMisuseRule::DistinctArguments, $query->facts->diagnostics[1]->rule);
    }

    public function testReportAdmitsTheClausesEachKindTakes(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $query = $semantics->analyze('SELECT count(*) FILTER (WHERE 1) OVER (), max(DISTINCT a, b), count(DISTINCT a), sum(a) FILTER (WHERE b IS NULL), row_number() OVER (ORDER BY a), abs(a) FROM t', [$semantics->analyze('CREATE TABLE t (a INTEGER NOT NULL, b TEXT)')]);

        self::assertSame([], $query->facts->diagnostics);
    }

    public function testReportIgnoresANameOutsideTheTableOrAWrongArgumentCount(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $unknown = $semantics->analyze('SELECT nosuch(1) OVER ()');
        $wrong = $semantics->analyze('SELECT abs(1, 2) OVER ()');

        self::assertSame([], $unknown->facts->diagnostics);
        self::assertCount(1, $wrong->facts->diagnostics);
        self::assertInstanceOf(WrongArgumentCount::class, $wrong->facts->diagnostics[0]);
    }

    public function testReportRecordsThroughTheDerivation(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $call = $semantics->analyze('SELECT abs(1) FILTER (WHERE 1) OVER ()')->field(0)->expression;
        $derivation = new Derivation($semantics->context());

        self::assertInstanceOf(FunctionCall::class, $call);
        (new CallChecks())->report($call, $derivation);
        $diagnostics = $derivation->facts()->diagnostics;
        self::assertCount(2, $diagnostics);
        self::assertInstanceOf(CallMisuse::class, $diagnostics[0]);
        self::assertSame(CallMisuseRule::ScalarAsWindow, $diagnostics[0]->rule);
        self::assertSame($call->name, $diagnostics[0]->function);
    }

    public function testBrokenAnswersTheRulesInAFixedOrderForEachKind(): void
    {
        $call = (new Semantics(Dialect::Sqlite))->analyze('SELECT count(DISTINCT a ORDER BY b) FILTER (WHERE 1) OVER () FROM t')->field(0)->expression;
        $checks = new CallChecks();

        self::assertInstanceOf(FunctionCall::class, $call);
        self::assertSame([CallMisuseRule::FilterOnWindowOnly, CallMisuseRule::OrderByWithoutAggregate, CallMisuseRule::DistinctInWindow], $checks->broken($call, 'w'));
        self::assertSame([CallMisuseRule::ScalarAsWindow, CallMisuseRule::FilterWithoutAggregate, CallMisuseRule::OrderByWithoutAggregate], $checks->broken($call, 's'));
        self::assertSame([CallMisuseRule::OrderByWithoutAggregate, CallMisuseRule::DistinctInWindow], $checks->broken($call, 'a'));
    }

    public function testBrokenOfAPlainCallDependsOnTheKindOnly(): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $plain = $semantics->analyze('SELECT abs(1)')->field(0)->expression;
        $distinct = $semantics->analyze("SELECT group_concat(DISTINCT a, ',') FROM t")->field(0)->expression;
        $checks = new CallChecks();

        self::assertInstanceOf(FunctionCall::class, $plain);
        self::assertInstanceOf(FunctionCall::class, $distinct);
        self::assertSame([CallMisuseRule::WindowWithoutOver], $checks->broken($plain, 'w'));
        self::assertSame([], $checks->broken($plain, 's'));
        self::assertSame([], $checks->broken($plain, 'a'));
        self::assertSame([CallMisuseRule::DistinctArguments], $checks->broken($distinct, 'a'));
        self::assertSame([], $checks->broken($distinct, 's'));
    }
}
