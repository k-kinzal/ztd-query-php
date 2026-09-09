<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite\Routine;

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
use SqlFaker\PostgreSql\Generation\Rewrite\Routine\TypeModifierRule;

#[CoversClass(TypeModifierRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
final class TypeModifierRuleTest extends TestCase
{
    /**
     * @param list<string> $expected
     * @param list<string> $argumentsOnly
     */
    #[DataProvider('providerModifiers')]
    public function testRewriteLimitsOnlyDirectTypeModifiers(TerminalSequence $input, array $expected, array $argumentsOnly): void
    {
        unset($argumentsOnly);
        $rule = new TypeModifierRule();
        $result = $rule->rewrite($input);
        self::assertSame($expected, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($input->terminals[0], $result->terminals[0]);
        self::assertSame($result, $rule->rewrite($result));
    }

    /**
     * @param list<string> $expected
     * @param list<string> $argumentsOnly
     */
    #[DataProvider('providerModifiers')]
    public function testArgumentsRetainsNestedNamedCallsAndListSeparators(TerminalSequence $input, array $expected, array $argumentsOnly): void
    {
        unset($expected);
        $rule = new TypeModifierRule();
        $result = $rule->arguments($input, 3);
        self::assertSame($argumentsOnly, $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, $rule->arguments($result, 3));
    }

    /**
     * @return iterable<array{TerminalSequence, list<string>, list<string>}>
     */
    public static function providerModifiers(): iterable
    {
        foreach (['AexprConst', 'func_application'] as $scope) {
            foreach (['COLON_EQUALS', 'EQUALS_GREATER', null] as $arrow) {
                foreach ([true, false] as $ordered) {
                    $trace = new DerivationTrace($scope);
                    $trace->expand(0, new Production([new NonTerminal('func_name'), new Terminal('('), new NonTerminal('func_arg_list'), new NonTerminal('opt_sort_clause'), new Terminal(')'), new Terminal('SCONST')]), 0);
                    $trace->expand(0, new Production([new Terminal('TYPE')]), 0);
                    $trace->expand(2, new Production([new NonTerminal('func_arg_list'), new Terminal(','), new NonTerminal('func_arg_expr')]), 1);
                    $trace->expand(2, new Production([new NonTerminal('func_arg_expr')]), 0);
                    $trace->expand(2, new Production($arrow === null ? [new NonTerminal('a_expr')] : [new NonTerminal('param_name'), new Terminal($arrow), new NonTerminal('a_expr')]), 0);
                    $first = 2;
                    if ($arrow !== null) {
                        $trace->expand(2, new Production([new Terminal('NAME')]), 0);
                        $first += 2;
                    }
                    $trace->expand($first, new Production([new Terminal('ICONST')]), 0);
                    $second = $first + 2;
                    $trace->expand($second, new Production([new NonTerminal('a_expr')]), 0);
                    $trace->expand($second, new Production([new NonTerminal('func_application')]), 0);
                    $trace->expand($second, new Production([new Terminal('INNER'), new Terminal('('), new NonTerminal('func_arg_list'), new Terminal(')')]), 0);
                    $trace->expand($second + 2, new Production([new NonTerminal('func_arg_expr')]), 0);
                    $trace->expand($second + 2, new Production([new NonTerminal('param_name'), new Terminal('COLON_EQUALS'), new NonTerminal('a_expr')]), 1);
                    $trace->expand($second + 2, new Production([new Terminal('NESTED_NAME')]), 0);
                    $trace->expand($second + 4, new Production([new Terminal('NESTED_VALUE')]), 0);
                    $trace->expand($second + 6, new Production($ordered ? [new Terminal('ORDER'), new Terminal('BY'), new Terminal('ICONST')] : []), 0);
                    $body = ['ICONST', ',', 'INNER', '(', 'NESTED_NAME', 'COLON_EQUALS', 'NESTED_VALUE', ')'];
                    $sort = $ordered ? ['ORDER', 'BY', 'ICONST'] : [];
                    $argumentsOnly = ['TYPE', '(', ...$body, ...$sort, ')', 'SCONST'];
                    $expected = $scope === 'AexprConst' ? ['TYPE', '(', ...$body, ')', 'SCONST'] : $trace->terminals()->names();
                    yield [$trace->terminals(), $expected, $argumentsOnly];
                }
            }
        }
    }

    public function testRewritePreservesConstantsWithoutModifierArguments(): void
    {
        $trace = new DerivationTrace('AexprConst');
        $trace->expand(0, new Production([new Terminal('TYPE'), new Terminal('SCONST')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new TypeModifierRule())->rewrite($input));
    }

    public function testArgumentsPreservesEmptyOrIncompleteArguments(): void
    {
        $empty = new TerminalSequence([], [], [], [new ProductionOccurrence(0, null, 'func_arg_list', 0), new ProductionOccurrence(1, 0, 'func_arg_expr', 0), new ProductionOccurrence(2, 1, 'a_expr', 0)]);
        self::assertSame($empty, (new TypeModifierRule())->arguments($empty, 0));
        $trace = new DerivationTrace('func_arg_list');
        $trace->expand(0, new Production([new NonTerminal('func_arg_expr')]), 0);
        $trace->expand(0, new Production([new Terminal('VALUE')]), 0);
        $input = $trace->terminals();
        self::assertSame($input, (new TypeModifierRule())->arguments($input, 0));
    }
}
