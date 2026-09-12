<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\AggregateArgumentRule;

#[CoversClass(AggregateArgumentRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class AggregateArgumentRuleTest extends TestCase
{
    #[DataProvider('providerModes')]
    public function testRewriteKeepsOrdinaryFunctionModesAndNormalizesAggregateInputModes(string $scope, string $mode, bool $ordered, bool $kept): void
    {
        $words = explode(' ', $mode);
        $modes = array_map(static fn (int $index, string $name): TerminalOccurrence =>
            new TerminalOccurrence($name, $index + 10, [0, 1, 2], ['aggr_args', $scope, 'arg_class']), array_keys($words), $words);
        $order = new TerminalOccurrence('ORDER', 20, [0], ['aggr_args']);
        $terminals = [...$modes, ...($ordered ? [$order] : [])];
        $input = new TerminalSequence($terminals, $terminals, [], [
            new ProductionOccurrence(0, null, 'aggr_args', 0), new ProductionOccurrence(1, 0, $scope, 0),
            new ProductionOccurrence(2, 1, 'arg_class', 0),
        ]);
        $rule = new AggregateArgumentRule();
        $result = $rule->rewrite($input);
        self::assertSame([...($kept ? $words : ['IN_P']), ...($ordered ? ['ORDER'] : [])], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{string, string, bool, bool}>
     */
    public static function providerModes(): iterable
    {
        yield ['aggr_arg', 'OUT_P', false, false];
        yield ['aggr_arg', 'INOUT', false, false];
        yield ['aggr_arg', 'IN_P OUT_P', false, false];
        yield ['aggr_arg', 'IN_P', true, true];
        yield ['aggr_arg', 'VARIADIC', false, true];
        yield ['aggr_arg', 'VARIADIC', true, false];
        yield ['func_arg', 'OUT_P', false, true];
    }
}
