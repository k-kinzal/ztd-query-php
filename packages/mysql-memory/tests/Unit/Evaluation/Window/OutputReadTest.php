<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Aggregate\Accumulation;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Window\Analytic;
use MySqlMemory\Evaluation\Window\OutputRead;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(OutputRead::class)]
#[Small]
final class OutputReadTest extends TestCase
{
    public function testAggregateRetainsUnreadFoldsOnlyWithinTheirExecutionFrame(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $domain = Domain::integer();
        $read = new OutputRead(new Analytic(null, new Accumulation(AggregateFunction::Count, [], false, $domain), [], 1, $domain), 0);
        $frame = new Frame($context);

        self::assertFalse($read->absent($frame));
        self::assertSame([1, 2, 1], [$read->aggregate($frame), $read->aggregate($frame), $read->aggregate(new Frame($context))]);
    }

    public function testAggregateKeepsRuntimeEmptyStateWithoutReadingItsArguments(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $argument = $this->createMock(Evaluable::class);
        $argument->expects(self::never())->method('evaluate');
        $domain = Domain::decimal(10, 0)->withNullable(true);
        $read = new OutputRead(new Analytic(null, new Accumulation(AggregateFunction::Sum, [$argument], false, $domain), [], 1, $domain), 0, false);

        self::assertTrue($read->absent($frame));
        self::assertNull($read->aggregate($frame));
    }

    public function testAggregateRetainsTheTotalOfAnUnreadAverageOverATable(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $domain = Domain::decimal(10, 4)->withNullable(true);
        $average = new Accumulation(AggregateFunction::Average, [new Constant(Domain::integer(), 10)], false, $domain);
        $read = new OutputRead(new Analytic(null, $average, [], 1, $domain), 0, true, true);

        self::assertSame(['10.0000', '20.0000'], [$read->aggregate($frame), $read->aggregate($frame)]);
    }

    public function testEvaluateReadsRetainedWindowSlotsAfterAnEmptyExecution(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0), [10]);
        $frame->windowSlots = [0 => 5];
        $read = new OutputRead(new Analytic(WindowFunctionKind::FirstValue, null, [], 1, Domain::integer()), 0);
        $current = $read->evaluate($frame);
        $frame->row = [];

        self::assertSame([10, 5, false], [$current, $read->evaluate($frame), $read->absent($frame)]);
    }

    public function testDomainPreservesTheWindowFunctionDomain(): void
    {
        $domain = Domain::double();
        $read = new OutputRead(new Analytic(WindowFunctionKind::PercentRank, null, [], 1, $domain), 2);

        self::assertSame($domain, $read->domain());
    }

    /**
     * @return iterable<string, array{WindowFunctionKind, int|float|null}>
     */
    public static function providerInitial(): iterable
    {
        yield 'row number' => [WindowFunctionKind::RowNumber, 1];
        yield 'rank' => [WindowFunctionKind::Rank, 1];
        yield 'dense rank' => [WindowFunctionKind::DenseRank, 1];
        yield 'tile' => [WindowFunctionKind::Tile, 1];
        yield 'percent rank' => [WindowFunctionKind::PercentRank, 0.0];
        yield 'cumulative distribution' => [WindowFunctionKind::CumulativeDistribution, 1.0];
        yield 'value function' => [WindowFunctionKind::FirstValue, null];
    }

    #[DataProvider('providerInitial')]
    public function testEvaluateDistinguishesAnUnreadWindowFromItsComputedSlots(WindowFunctionKind $kind, int|float|null $initial): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $read = new OutputRead(new Analytic($kind, null, [], 1, Domain::integer()), 1);

        self::assertSame([$initial, null, 5], [$read->evaluate(new Frame($context, [7])), $read->evaluate(new Frame($context, [7, null])), $read->evaluate(new Frame($context, [7, 5]))]);
    }

    public function testAbsentInspectsWindowStateWithoutReadingItsArgument(): void
    {
        $session = (new Instance())->connect();
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $argument = $this->createMock(Evaluable::class);
        $argument->expects(self::never())->method('evaluate');
        $read = new OutputRead(new Analytic(WindowFunctionKind::FirstValue, null, [$argument], 1, Domain::integer()), 0);

        self::assertSame([true, true, false], [$read->absent(new Frame($context)), $read->absent(new Frame($context, [null])), $read->absent(new Frame($context, [10]))]);
    }

    /**
     * @return iterable<string, array{WindowFunctionKind, int, int|null}>
     */
    public static function providerValues(): iterable
    {
        yield 'first' => [WindowFunctionKind::FirstValue, 1, 10];
        yield 'last' => [WindowFunctionKind::LastValue, 1, 10];
        yield 'first ordinal' => [WindowFunctionKind::NthValue, 1, 10];
        yield 'later ordinal' => [WindowFunctionKind::NthValue, 2, null];
        yield 'current lag' => [WindowFunctionKind::Lag, 0, 10];
        yield 'default lag' => [WindowFunctionKind::Lag, 1, 7];
        yield 'current lead' => [WindowFunctionKind::Lead, 0, 10];
        yield 'default lead' => [WindowFunctionKind::Lead, 1, 7];
    }

    #[DataProvider('providerValues')]
    public function testInitialReadsTheArgumentOrDefaultAtTheInitialRowPosition(WindowFunctionKind $kind, int $count, ?int $expected): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context($session->modes(), $session->diagnostics, $session->variables, 0.0));
        $domain = Domain::integer();
        $read = new OutputRead(new Analytic($kind, null, [new Constant($domain, 10), new Constant($domain, 7)], $count, $domain), 0);

        self::assertSame($expected, $read->initial($frame));
    }
}
