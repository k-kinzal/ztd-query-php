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
    public function testBareWrapsTheSelectProductionOf57(): void
    {
        $select = new TerminalOccurrence('SELECT_SYM', 10, [0], ['table_factor']);
        $star = new TerminalOccurrence('*', 11, [0, 2], ['table_factor', 'select_item_list']);
        $input = new TerminalSequence([$select, $star], [$select, $star], [], [
            new ProductionOccurrence(0, null, 'table_factor', 1),
            new ProductionOccurrence(1, 0, 'select_options', 0),
            new ProductionOccurrence(2, 0, 'select_item_list', 0),
        ]);
        $rule = new LegacyDerivedTableRule();
        $result = $rule->rewrite($input);
        self::assertSame(['(', 'SELECT_SYM', '*', ')', 'AS', 'IDENT_QUOTED'], $result->names());
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @param list<string> $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('providerDerivedRoles')]
    public function testParenthesizedCompletesQueryRolesWithoutWrappingTheirHead(TerminalSequence $input, array $expected): void
    {
        $rule = new LegacyDerivedTableRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($result, $rule->rewrite($result));
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
    }

    /**
     * @return iterable<string, array{TerminalSequence, list<string>}>
     */
    public static function providerDerivedRoles(): iterable
    {
        foreach (['query', 'union', 'join', 'alias', 'order'] as $role) {
            $productions = [
                new ProductionOccurrence(0, null, 'table_factor', 2),
                new ProductionOccurrence(1, 0, 'select_derived_union', $role === 'union' ? 1 : 0),
                ...($role === 'union' ? [new ProductionOccurrence(2, 1, 'select_derived_union', 0)] : []),
                new ProductionOccurrence(3, $role === 'union' ? 2 : 1, 'select_derived', 0),
                new ProductionOccurrence(4, 3, 'derived_table_list', 0),
                new ProductionOccurrence(5, 4, 'table_factor', $role === 'query' ? 1 : 0),
                new ProductionOccurrence(6, 5, $role === 'query' ? 'select_options' : 'table_ident', 0),
                new ProductionOccurrence(7, 0, 'opt_table_alias', 0),
                new ProductionOccurrence(8, $role === 'union' ? 2 : 1, 'opt_union_order_or_limit', 0),
            ];
            $sequence = new TerminalSequence([], [], [], $productions);
            $tokens = [$sequence->insertedFor('(', 0, 'fixture')];
            foreach ($role === 'query' ? ['SELECT_SYM', '*'] : ['IDENT'] as $index => $name) {
                $tokens[] = $sequence->insertedFor($name, 5, 'fixture', $index + 1);
            }
            if ($role === 'union') {
                foreach (['UNION_SYM', 'SELECT_SYM', '*'] as $index => $name) {
                    $tokens[] = $sequence->insertedFor($name, 1, 'fixture', $index + 3);
                }
            }
            if ($role === 'order') {
                $tokens[] = $sequence->insertedFor('LIMIT', 8, 'fixture', 6);
                $tokens[] = $sequence->insertedFor('NUM', 8, 'fixture', 7);
            }
            $tokens[] = $sequence->insertedFor(')', 0, 'fixture', 8);
            if ($role === 'alias') {
                $tokens[] = $sequence->insertedFor('AS', 7, 'fixture', 9);
                $tokens[] = $sequence->insertedFor('IDENT_QUOTED', 7, 'fixture', 10);
            }
            $expected = match ($role) {
                'query' => ['(', 'SELECT_SYM', '*', ')', 'AS', 'IDENT_QUOTED'],
                'union' => ['(', 'SELECT_SYM', '*', 'FROM', 'IDENT', 'UNION_SYM', 'SELECT_SYM', '*', ')', 'AS', 'IDENT_QUOTED'],
                'join' => ['(', 'IDENT', ')'],
                'alias' => ['(', 'SELECT_SYM', '*', 'FROM', 'IDENT', ')', 'AS', 'IDENT_QUOTED'],
                'order' => ['(', 'SELECT_SYM', '*', 'FROM', 'IDENT', 'LIMIT', 'NUM', ')', 'AS', 'IDENT_QUOTED'],
            };
            yield $role => [new TerminalSequence($tokens, $tokens, [], $productions), $expected];
        }
    }

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
