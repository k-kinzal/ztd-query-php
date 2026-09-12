<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Partition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Rewrite\Partition\ValueArityRule;
use SqlFaker\MySql\Generation\Rewrite\Partition\ValueShape;

#[CoversClass(ValueArityRule::class)]
#[UsesClass(ValueShape::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class ValueArityRuleTest extends TestCase
{
    #[DataProvider('providerDeclarations')]
    public function testRewriteCoordinatesAllValuesInTheSamePartitionList(TerminalSequence $input, string $expected): void
    {
        $rule = new ValueArityRule();
        $result = $rule->rewrite($input);
        self::assertSame(explode(' ', $expected), $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertCount(count($result->terminals), array_unique(array_column($result->terminals, 'id')));
        self::assertSame($result, $rule->rewrite($result));
        $retained = array_values(array_filter($result->terminals, static fn (TerminalOccurrence $token): bool => $token->id >= 0));
        self::assertSame(array_map(static fn (TerminalOccurrence $token): TerminalOccurrence => $input->original[$token->id], $retained), $retained);
    }

    /**
     * @return iterable<array{TerminalSequence, string}>
     */
    public static function providerDeclarations(): iterable
    {
        $cases = [
            ['RANGE_SYM COLUMNS ( IDENT , IDENT , IDENT )', 'VALUES LESS_SYM THAN_SYM MAX_VALUE_SYM', 'VALUES LESS_SYM THAN_SYM ( NUM )', 'VALUES LESS_SYM THAN_SYM ( MAX_VALUE_SYM , MAX_VALUE_SYM , MAX_VALUE_SYM ) VALUES LESS_SYM THAN_SYM ( NUM , NUM , NUM )'],
            ['LIST_SYM ( IDENT )', 'VALUES IN_SYM ( ( NUM , NUM ) )', 'VALUES IN_SYM ( NUM )', 'VALUES IN_SYM ( NUM , NUM ) VALUES IN_SYM ( NUM )'],
            ['LIST_SYM COLUMNS ( IDENT , IDENT )', 'VALUES IN_SYM ( NUM )', 'VALUES IN_SYM ( ( NUM , NUM , NUM ) )', 'VALUES IN_SYM ( ( NUM , NUM ) ) VALUES IN_SYM ( ( NUM , NUM ) )'],
            [null, 'VALUES IN_SYM ( ( NUM ) , ( NUM , NUM , NUM ) )', 'VALUES IN_SYM ( NUM )', 'VALUES IN_SYM ( ( NUM , NUM ) , ( NUM , NUM ) ) VALUES IN_SYM ( ( NUM , NUM ) )'],
            [null, 'VALUES IN_SYM ( NUM , NUM )', 'VALUES IN_SYM ( ( NUM , NUM ) )', 'VALUES IN_SYM ( NUM , NUM ) VALUES IN_SYM ( NUM , NUM )'],
            [null, 'VALUES LESS_SYM THAN_SYM ( NUM , NUM )', 'VALUES LESS_SYM THAN_SYM MAX_VALUE_SYM', 'VALUES LESS_SYM THAN_SYM ( NUM , NUM ) VALUES LESS_SYM THAN_SYM ( MAX_VALUE_SYM , MAX_VALUE_SYM )'],
            [null, 'VALUES LESS_SYM THAN_SYM MAX_VALUE_SYM', 'VALUES LESS_SYM THAN_SYM ( NUM , NUM )', 'VALUES LESS_SYM THAN_SYM MAX_VALUE_SYM VALUES LESS_SYM THAN_SYM ( NUM )'],
        ];
        foreach ($cases as [$declaration, $first, $second, $expected]) {
            $productions = [new ProductionOccurrence(0, null, 'partition_clause', 0), new ProductionOccurrence(1, 0, 'part_type_def', 0), new ProductionOccurrence(2, 0, 'part_def_list', 0), new ProductionOccurrence(3, 2, 'opt_part_values', 0), new ProductionOccurrence(4, 2, 'opt_part_values', 0)];
            $tokens = [];
            foreach ([1 => $declaration, 3 => $first, 4 => $second] as $id => $text) {
                if ($text === null) {
                    continue;
                }
                $ancestors = $id === 1 ? [0, 1] : [0, 2, $id];
                $rules = $id === 1 ? ['partition_clause', 'part_type_def'] : ['partition_clause', 'part_def_list', 'opt_part_values'];
                foreach (explode(' ', $text) as $name) {
                    $tokens[] = new TerminalOccurrence($name, count($tokens), $ancestors, $rules);
                }
            }
            yield [new TerminalSequence($tokens, $tokens, productions: $productions), ($declaration === null ? '' : $declaration . ' ') . $expected];
        }
    }

    public function testRewriteLeavesEmptyAndUnscopedFragmentsAlone(): void
    {
        $rule = new ValueArityRule();
        $empty = new TerminalSequence([], productions: [new ProductionOccurrence(0, null, 'opt_part_values', 0)]);
        self::assertSame($empty, $rule->rewrite($empty));
        $fragment = new TerminalSequence([new TerminalOccurrence('VALUES', 1, [0], ['opt_part_values'])], productions: $empty->productions);
        self::assertSame($fragment, $rule->rewrite($fragment));
        self::assertNull($rule->declaredWidth($fragment, 0));
        self::assertNull($rule->declaredWidth($fragment, null));
    }

    public function testDeclaredWidthReadsTheColumnsOfTheOwningClause(): void
    {
        $names = ['LIST_SYM', 'COLUMNS', '(', 'IDENT', ',', 'IDENT', ')'];
        $tokens = array_map(static fn (string $name, int $id): TerminalOccurrence => new TerminalOccurrence($name, $id, [0, 1], ['partition_clause', 'part_type_def']), $names, array_keys($names));
        $input = new TerminalSequence($tokens, productions: [new ProductionOccurrence(0, null, 'partition_clause', 0), new ProductionOccurrence(1, 0, 'part_type_def', 0)]);
        self::assertSame(2, (new ValueArityRule())->declaredWidth($input, 0));
        self::assertNull((new ValueArityRule())->declaredWidth($input, 1));
    }
}
