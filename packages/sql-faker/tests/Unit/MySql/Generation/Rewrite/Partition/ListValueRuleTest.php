<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Partition\ListValueRule;

#[CoversClass(ListValueRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ListValueRuleTest extends TestCase
{
    #[DataProvider('providerValues')]
    public function testRewriteDisambiguatesListExpressionsAndPreservesRangeBounds(string $scope, string $first, string $expected): void
    {
        $item = new TerminalOccurrence($first, 10, [0, 1], [$scope, 'part_value_item']);
        $input = new TerminalSequence([$item], productions: [new ProductionOccurrence(0, null, $scope, 0), new ProductionOccurrence(1, 0, 'part_value_item', 0)]);
        $result = (new ListValueRule())->rewrite($input);
        self::assertSame($expected === '+' ? ['+', '('] : [$expected], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($item->id, $result->terminals[count($result->terminals) - 1]->id);
        self::assertSame($result, (new ListValueRule())->rewrite($result));
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function providerValues(): array
    {
        return [['part_values_in', 'MAX_VALUE_SYM', 'NUM'], ['part_func_max', 'MAX_VALUE_SYM', 'MAX_VALUE_SYM'], ['part_values_in', '(', '+'], ['part_func_max', '(', '('], ['part_values_in', 'NUM', 'NUM']];
    }

    public function testRewritePreservesAnEmptyListItem(): void
    {
        $input = new TerminalSequence([], productions: [new ProductionOccurrence(0, null, 'part_value_item', 0)]);
        self::assertSame($input, (new ListValueRule())->rewrite($input));
    }
}
