<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\Partition\FieldListRule;

#[CoversClass(FieldListRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class FieldListRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerLists')]
    public function testRewriteKeepsUpToSixteenPartitionFieldsAndPreservesOtherLists(TerminalSequence $input, array $expected): void
    {
        $rule = new FieldListRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[1], $result->terminals[1]);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{TerminalSequence, list<string>}>
     */
    public static function providerLists(): iterable
    {
        foreach ([['part_type_def', 'name_list', true], ['opt_sub_part', 'name_list', true], ['part_field_list', 'part_field_item_list', true], ['opt_sub_part', 'sub_part_field_list', true], ['ordinary', 'name_list', false]] as [$scope, $list, $limited]) {
            foreach ([1, 16, 17, 19] as $count) {
                $trace = new DerivationTrace($scope);
                $trace->expand(0, new Production([new Terminal('PREFIX'), new NonTerminal($list), new Terminal('TAIL')]), 0);
                $symbols = [];
                $expected = ['PREFIX'];
                for ($index = 0; $index < $count; ++$index) {
                    if ($index !== 0) {
                        $symbols[] = new Terminal(',');
                    }
                    $symbols[] = new NonTerminal('ident');
                    if (!$limited || $index < 16) {
                        if ($index !== 0) {
                            $expected[] = ',';
                        }
                        $expected[] = 'FIELD_' . $index;
                    }
                }
                $trace->expand(1, new Production($symbols), 0);
                for ($index = 0; $index < $count; ++$index) {
                    $trace->expand(1 + 2 * $index, new Production([new Terminal('FIELD_' . $index)]), 0);
                }
                yield [$trace->terminals(), [...$expected, 'TAIL']];
            }
        }
    }
}
