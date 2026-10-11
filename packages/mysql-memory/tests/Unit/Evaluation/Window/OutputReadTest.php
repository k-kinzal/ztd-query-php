<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

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
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind;

#[CoversClass(OutputRead::class)]
#[Small]
final class OutputReadTest extends TestCase
{
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
