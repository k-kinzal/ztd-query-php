<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
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
}
