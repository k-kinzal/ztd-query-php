<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Rewrite\Column\NumericContextRule;

#[CoversClass(NumericContextRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class NumericContextRuleTest extends TestCase
{
    /**
     * @param list<string> $rules
     */
    #[DataProvider('providerContexts')]
    public function testRewritePreservesNestedIntegersAndRestrictsDirectContextValues(array $rules, string $name, string $expected): void
    {
        $terminal = new TerminalOccurrence($name, 10, array_keys($rules), $rules);
        $sequence = new TerminalSequence([$terminal], [$terminal]);
        $rule = new NumericContextRule();
        $result = $rule->rewrite($sequence);
        self::assertSame([$expected], $result->names());
        self::assertSame($terminal->id, $result->terminals[0]->id);
        self::assertSame($terminal->ancestors, $result->terminals[0]->ancestors);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return list<array{list<string>, string, string}>
     */
    public static function providerContexts(): array
    {
        return [
            [['opt_float', 'Iconst'], 'ICONST', 'FLOAT_PRECISION_NUMBER'],
            [['alter_table_cmd', 'Iconst'], 'ICONST', 'COLUMN_POSITION_NUMBER'],
            [['alter_table_cmd', 'SignedIconst', 'Iconst'], 'ICONST', 'ICONST'],
            [['a_expr', 'Iconst'], 'ICONST', 'ICONST'],
            [[], 'ICONST', 'ICONST'],
            [['opt_float', 'Iconst'], 'FCONST', 'FCONST'],
        ];
    }
}
