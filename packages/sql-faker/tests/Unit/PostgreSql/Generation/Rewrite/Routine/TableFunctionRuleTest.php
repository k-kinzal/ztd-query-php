<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\TableFunctionRule;

#[CoversClass(TableFunctionRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class TableFunctionRuleTest extends TestCase
{
    /**
     * @param list<string> $mode
     * @param list<string> $expected
     */
    #[DataProvider('providerModes')]
    public function testRewriteRestrictsOnlyInputArgumentsOfTableFunctions(array $mode, array $expected, bool $table, string $parameters): void
    {
        $trace = new DerivationTrace('CreateFunctionStmt');
        $trace->expand(0, new Production([new NonTerminal($parameters), new NonTerminal($table ? 'table_func_column_list' : 'func_return')]), 0);
        $trace->expand(0, new Production([new Terminal('('), new NonTerminal('func_arg'), new Terminal(')')]), 0);
        $trace->expand(1, new Production([new NonTerminal('arg_class'), new Terminal('IDENT'), new Terminal('INT_P')]), 0);
        $trace->expand(1, new Production(array_map(static fn (string $name): Terminal => new Terminal($name), $mode)), 0);
        $trace->expand(count($mode) + 4, new Production([new Terminal('INT_P')]), 0);
        $input = $trace->terminals();
        $rule = new TableFunctionRule();
        $result = $rule->rewrite($input);
        self::assertSame(['(', ...$expected, 'IDENT', 'INT_P', ')', 'INT_P'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @return iterable<array{list<string>, list<string>, bool, string}>
     */
    public static function providerModes(): iterable
    {
        foreach ([['OUT_P'], ['INOUT'], ['IN_P', 'OUT_P']] as $mode) {
            yield [$mode, ['IN_P'], true, 'func_args_with_defaults'];
            yield [$mode, $mode, false, 'func_args_with_defaults'];
            yield [$mode, $mode, true, 'func_args'];
        }
        foreach ([[], ['IN_P'], ['VARIADIC']] as $mode) {
            yield [$mode, $mode, true, 'func_args_with_defaults'];
        }
    }
}
