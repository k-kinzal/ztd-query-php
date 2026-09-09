<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\Expression\TableValueConstructorRule;

#[CoversClass(TableValueConstructorRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class TableValueConstructorRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerRows')]
    public function testRewriteCompletesQueryRowsAndPreservesInsertRows(TerminalSequence $input, array $expected): void
    {
        $rule = new TableValueConstructorRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[0], $result->terminals[0]);
        self::assertSame($result, $rule->rewrite($result));
        $ids = array_column($result->terminals, 'id');
        self::assertSame($ids, array_values(array_unique($ids)));
    }

    /**
     * @return iterable<array{TerminalSequence, list<string>}>
     */
    public static function providerRows(): iterable
    {
        $contexts = [
            [['query_expression', 'query_expression_body', 'query_primary', 'table_value_constructor'], true],
            [['explain_stmt', 'query_expression', 'query_primary', 'table_value_constructor'], true],
            [['insert_query_expression', 'query_expression_with_opt_locking_clauses', 'query_expression', 'query_expression_body', 'query_primary', 'table_value_constructor'], false],
            [['insert_query_expression', 'query_expression', 'query_expression_body', 'query_expression_parens', 'query_expression_parens', 'query_expression_with_opt_locking_clauses', 'query_expression', 'query_expression_body', 'query_primary', 'table_value_constructor'], false],
            [['insert_query_expression', 'query_expression', 'query_specification', 'subquery', 'query_expression', 'query_primary', 'table_value_constructor'], true],
            [['insert_query_expression', 'query_expression', 'with_clause', 'common_table_expr', 'query_expression', 'query_primary', 'table_value_constructor'], true],
            [['ordinary'], false],
        ];
        foreach ($contexts as [$path, $limited]) {
            foreach (['empty', 'default', 'expression', 'number'] as $value) {
                foreach ([1, 3] as $count) {
                    $trace = new DerivationTrace('root');
                    foreach ([...$path, 'values_row_list'] as $scope) {
                        $trace->expand(0, new Production([new NonTerminal($scope)]), 0);
                    }
                    $rows = [];
                    for ($index = 0; $index < $count; ++$index) {
                        if ($index !== 0) {
                            $rows[] = new Terminal(',');
                        }
                        $rows[] = new NonTerminal('row_value_explicit');
                    }
                    $trace->expand(0, new Production($rows), 0);
                    $expected = [];
                    for ($index = 0; $index < $count; ++$index) {
                        $offset = $index * ($value === 'empty' ? 4 : 5);
                        $trace->expand($offset, new Production([new Terminal('ROW_SYM'), new Terminal('('), new NonTerminal('opt_values'), new Terminal(')')]), 0);
                        $trace->expand($offset + 2, new Production($value === 'empty' ? [] : [new NonTerminal('values')]), 0);
                        if ($value !== 'empty') {
                            $trace->expand($offset + 2, new Production([new NonTerminal('expr_or_default')]), 0);
                            $trace->expand($offset + 2, new Production($value === 'default' ? [new Terminal('DEFAULT_SYM')] : [new NonTerminal('expr')]), 0);
                            if ($value !== 'default') {
                                $trace->expand($offset + 2, new Production([new Terminal($value === 'expression' ? 'DEFAULT_SYM' : 'NUM')]), 0);
                            }
                        }
                        if ($index !== 0) {
                            $expected[] = ',';
                        }
                        array_push($expected, 'ROW_SYM', '(');
                        if ($value === 'empty' && $limited || $value === 'default' && $limited) {
                            $expected[] = 'NULL_SYM';
                        } elseif ($value !== 'empty') {
                            $expected[] = $value === 'number' ? 'NUM' : 'DEFAULT_SYM';
                        }
                        $expected[] = ')';
                    }
                    yield [$trace->terminals(), $expected];
                }
            }
        }
    }

    public function testRewriteRecordsEmptyProductionAndReplacementProvenance(): void
    {
        $trace = new DerivationTrace('table_value_constructor');
        $trace->expand(0, new Production([new NonTerminal('row_value_explicit')]), 0);
        $trace->expand(0, new Production([new Terminal('ROW_SYM'), new Terminal('('), new NonTerminal('opt_values'), new Terminal(')')]), 0);
        $trace->expand(2, new Production([]), 7);
        $input = $trace->terminals();
        $result = (new TableValueConstructorRule())->rewrite($input);
        self::assertSame(['sql/sql_resolver.cc:resolve_table_value_constructor_values'], $result->rewrites);
        self::assertSame('sql/sql_resolver.cc:resolve_table_value_constructor_values', $result->terminals[2]->rewrite);
        self::assertSame([0, 1, 4], $result->terminals[2]->ancestors);
        self::assertSame(['table_value_constructor', 'row_value_explicit', 'opt_values'], $result->terminals[2]->rules);
        self::assertSame([2, 3], $result->range(4));
        self::assertSame(-1, $result->terminals[2]->id);
        self::assertSame(7, $result->productions[2]->ordinal);
        self::assertSame($input->terminals[2], $result->terminals[3]);
    }

    public function testRewritePreservesAbsentRowsAndMissingValueProductions(): void
    {
        $empty = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'row_value_explicit', 0)]);
        self::assertSame($empty, (new TableValueConstructorRule())->rewrite($empty));
        $trace = new DerivationTrace('table_value_constructor');
        $trace->expand(0, new Production([new NonTerminal('row_value_explicit')]), 0);
        $trace->expand(0, new Production([new Terminal('ROW_SYM'), new Terminal('('), new Terminal(')')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new TableValueConstructorRule())->rewrite($input));
    }

    public function testRewriteCompletesBothRowsOfAnInsertSetOperation(): void
    {
        $trace = new DerivationTrace('insert_query_expression');
        $trace->expand(0, new Production([new NonTerminal('query_expression_body')]), 0);
        $trace->expand(0, new Production([new NonTerminal('query_expression_body'), new Terminal('UNION_SYM'), new NonTerminal('query_expression_body')]), 2);
        $trace->expand(0, new Production([new NonTerminal('table_value_constructor')]), 0);
        $trace->expand(0, new Production([new NonTerminal('row_value_explicit')]), 0);
        $trace->expand(0, new Production([new Terminal('ROW_SYM'), new Terminal('('), new NonTerminal('opt_values'), new Terminal(')')]), 0);
        $trace->expand(2, new Production([]), 0);
        $trace->expand(4, new Production([new NonTerminal('table_value_constructor')]), 0);
        $trace->expand(4, new Production([new NonTerminal('row_value_explicit')]), 0);
        $trace->expand(4, new Production([new Terminal('ROW_SYM'), new Terminal('('), new NonTerminal('opt_values'), new Terminal(')')]), 0);
        $trace->expand(6, new Production([]), 0);
        $input = $trace->terminals();
        $rule = new TableValueConstructorRule();
        $result = $rule->rewrite($input);
        self::assertSame(['ROW_SYM', '(', 'NULL_SYM', ')', 'UNION_SYM', 'ROW_SYM', '(', 'NULL_SYM', ')'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->rewrite($result));
    }

    #[DataProvider('providerInsertScopes')]
    public function testIsInsertSourceExcludesSetOperations(TerminalSequence $sequence, TerminalOccurrence $origin, bool $expected): void
    {
        self::assertSame($expected, (new TableValueConstructorRule())->isInsertSource($sequence, $origin));
    }

    /**
     * @return iterable<array{TerminalSequence, TerminalOccurrence, bool}>
     */
    public static function providerInsertScopes(): iterable
    {
        foreach ([0, 1, 2, 3] as $bodyCount) {
            foreach ([0, 10] as $parent) {
                $productions = [new ProductionOccurrence(0, null, 'insert_query_expression', 0), new ProductionOccurrence(1, 0, 'table_value_constructor', 0)];
                for ($index = 0; $index < $bodyCount; ++$index) {
                    $productions[] = new ProductionOccurrence($index + 2, $parent, 'query_expression_body', 0);
                }
                $productions[] = new ProductionOccurrence(8, 0, 'unrelated', 0);
                $origin = new TerminalOccurrence('ROW_SYM', 20, [0, 1], ['insert_query_expression', 'table_value_constructor']);
                yield [new TerminalSequence([$origin], [], [], $productions), $origin, $parent !== 0 || $bodyCount < 2];
            }
        }
        yield [new TerminalSequence([]), new TerminalOccurrence('ROW_SYM', 0), false];
    }
}
