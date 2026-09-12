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
use SqlFaker\MySql\Generation\Rewrite\Partition\DefinitionRule;

#[CoversClass(DefinitionRule::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
final class DefinitionRuleTest extends TestCase
{
    #[DataProvider('providerDeclaredKinds')]
    public function testDeclaredKindUsesTheEnclosingType(string $name, ?int $clause, ?string $expected): void
    {
        $input = new TerminalSequence([new TerminalOccurrence($name, 10, [0, 1], ['partition_clause', 'part_type_def'])], productions: [new ProductionOccurrence(0, null, 'partition_clause', 0), new ProductionOccurrence(1, 0, 'part_type_def', 0)]);
        self::assertSame($expected, (new DefinitionRule())->declaredKind($input, $clause));
    }

    /**
     * @return list<array{string, int|null, string|null}>
     */
    public static function providerDeclaredKinds(): array
    {
        return [['RANGE_SYM', 0, 'RANGE'], ['LIST_SYM', 0, 'LIST'], ['KEY_SYM', 0, 'HASH'], ['HASH_SYM', 0, 'HASH'], ['RANGE_SYM', null, null], ['LIST_SYM', 1, null]];
    }

    public function testInferCountRemovesOnlyTheExplicitCount(): void
    {
        $input = new TerminalSequence([new TerminalOccurrence('HASH_SYM', 10, [0], ['partition_clause']), new TerminalOccurrence('PARTITIONS_SYM', 11, [0, 1], ['partition_clause', 'opt_num_parts']), new TerminalOccurrence('NUM', 12, [0, 1], ['partition_clause', 'opt_num_parts'])], productions: [new ProductionOccurrence(0, null, 'partition_clause', 0), new ProductionOccurrence(1, 0, 'opt_num_parts', 0)]);
        $rule = new DefinitionRule();
        self::assertSame($input, $rule->inferCount($input, null));
        $result = $rule->inferCount($input, 0);
        self::assertSame(['HASH_SYM'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, $rule->inferCount($result, 0));
    }

    public function testReplaceValuesPreservesFragmentsWithoutAnInsertionPoint(): void
    {
        $input = new TerminalSequence([], productions: [new ProductionOccurrence(0, null, 'part_definition', 0), new ProductionOccurrence(1, 0, 'opt_part_values', 0)]);
        self::assertSame($input, (new DefinitionRule())->replaceValues($input, 0, 1, 'LIST'));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerKinds')]
    public function testRewriteKeepsOneKindAcrossTheDefinitionList(TerminalSequence $input, int $values, array $expected): void
    {
        $result = (new DefinitionRule())->rewrite($input);
        $range = $result->range($values);
        self::assertSame($expected, $range === null ? [] : array_slice($result->names(), $range[0], $range[1] - $range[0]));
        self::assertNotContains('PARTITIONS_SYM', $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertCount(count($result->terminals), array_unique(array_column($result->terminals, 'id')));
        self::assertSame($result, (new DefinitionRule())->rewrite($result));
    }

    /**
     * @return iterable<array{TerminalSequence, int, list<string>}>
     */
    public static function providerKinds(): iterable
    {
        $cases = [
            ['KEY_SYM', 'RANGE', 'LIST', []],
            ['HASH_SYM', 'HASH', 'HASH', []],
            ['RANGE_SYM', 'HASH', 'LIST', ['VALUES', 'LESS_SYM', 'THAN_SYM', 'MAX_VALUE_SYM']],
            ['LIST_SYM', 'RANGE', 'HASH', ['VALUES', 'IN_SYM', '(', 'NUM', ')']],
            [null, 'RANGE', 'HASH', ['VALUES', 'LESS_SYM', 'THAN_SYM', 'MAX_VALUE_SYM']],
            [null, 'LIST', 'HASH', ['VALUES', 'IN_SYM', '(', 'NUM', ')']],
            [null, 'HASH', 'RANGE', []],
        ];
        foreach ($cases as [$type, $first, $second, $expected]) {
            if ($type === null) {
                $root = 'alter_table';
                $tokens = [];
            } else {
                $root = 'partition_clause';
                $tokens = [new TerminalOccurrence($type, 100, [0, 1], [$root, 'part_type_def']), new TerminalOccurrence('PARTITIONS_SYM', 101, [0, 2], [$root, 'opt_num_parts']), new TerminalOccurrence('NUM', 102, [0, 2], [$root, 'opt_num_parts'])];
            }
            $productions = [new ProductionOccurrence(0, null, $root, 0), new ProductionOccurrence(1, 0, 'part_type_def', 0), new ProductionOccurrence(2, 0, 'opt_num_parts', 0), new ProductionOccurrence(3, 0, 'part_def_list', 0)];
            foreach ([$first, $second] as $index => $kind) {
                $id = 4 + $index * 3;
                $productions[] = new ProductionOccurrence($id, 3, 'part_definition', 0);
                $productions[] = new ProductionOccurrence($id + 1, $id, 'ident', 0);
                $productions[] = new ProductionOccurrence($id + 2, $id, 'opt_part_values', 0);
                $tokens[] = new TerminalOccurrence('PARTITION_SYM', 110 + $index * 10, [0, 3, $id], [$root, 'part_def_list', 'part_definition']);
                $tokens[] = new TerminalOccurrence('IDENT', 111 + $index * 10, [0, 3, $id, $id + 1], [$root, 'part_def_list', 'part_definition', 'ident']);
                $names = match ($kind) {
                    'LIST' => ['VALUES', 'IN_SYM', '(', 'NUM', ')'], 'RANGE' => ['VALUES', 'LESS_SYM', 'THAN_SYM', 'MAX_VALUE_SYM'], 'HASH' => []
                };
                foreach ($names as $offset => $name) {
                    $tokens[] = new TerminalOccurrence($name, 112 + $index * 10 + $offset, [0, 3, $id, $id + 2], [$root, 'part_def_list', 'part_definition', 'opt_part_values']);
                }
            }
            $input = new TerminalSequence($tokens, productions: $productions);
            yield [$input, 6, $expected];
            yield [$input, 9, $expected];
        }
    }

    public function testRewritePreservesAStandaloneDefinitionWithoutAList(): void
    {
        $input = new TerminalSequence([new TerminalOccurrence('PARTITION_SYM', 10, [0], ['part_definition'])], productions: [new ProductionOccurrence(0, null, 'part_definition', 0), new ProductionOccurrence(1, 0, 'opt_part_values', 0)]);
        self::assertSame($input, (new DefinitionRule())->rewrite($input));
        $empty = new TerminalSequence([], productions: [new ProductionOccurrence(0, null, 'part_definition', 0)]);
        self::assertSame($empty, (new DefinitionRule())->rewrite($empty));
    }
}
