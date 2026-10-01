<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Generation\Rewrite\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Query\LegacyDerivedTableRule;

#[CoversClass(LegacyDerivedTableRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class LegacyDerivedTableRuleTest extends TestCase
{
    public function testRewriteMakesBareSelectOperandsIndependentTableFactors(): void
    {
        $select = new TerminalOccurrence('SELECT_SYM', 10, [0, 1], ['table_factor', 'select_derived_init']);
        $star = new TerminalOccurrence('*', 11, [0, 2], ['table_factor', 'select_derived2']);
        $input = new TerminalSequence([$select, $star], [$select, $star], [], [
            new ProductionOccurrence(0, null, 'table_factor', 1),
            new ProductionOccurrence(1, 0, 'select_derived_init', 0),
            new ProductionOccurrence(2, 0, 'select_derived2', 0),
        ]);
        $rule = new LegacyDerivedTableRule();
        $result = $rule->rewrite($input);
        self::assertSame(['(', 'SELECT_SYM', '*', ')', 'AS', 'IDENT_QUOTED'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    public function testRewritePreservesOrdinaryTableReferences(): void
    {
        $name = new TerminalOccurrence('IDENT', 10, [0, 1], ['table_factor', 'table_ident']);
        $input = new TerminalSequence([$name], [$name], [], [
            new ProductionOccurrence(0, null, 'table_factor', 0),
            new ProductionOccurrence(1, 0, 'table_ident', 0),
        ]);
        self::assertSame($input, (new LegacyDerivedTableRule())->rewrite($input));
    }
}
