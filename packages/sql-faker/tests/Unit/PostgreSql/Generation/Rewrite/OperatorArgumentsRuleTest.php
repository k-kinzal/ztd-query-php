<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\PostgreSql\Generation\Rewrite\OperatorArgumentsRule;

#[CoversClass(OperatorArgumentsRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\NonTerminal::class)]
final class OperatorArgumentsRuleTest extends TestCase
{
    public function testRewriteCompletesOnlyAUnaryOperatorSignature(): void
    {
        $trace = new DerivationTrace('oper_argtypes');
        $trace->expand(0, new Production([new Terminal('('), new Terminal('NUMERIC'), new Terminal('('), new Terminal('ICONST'), new Terminal(','), new Terminal('ICONST'), new Terminal(')'), new Terminal(')')]), 0);
        $input = $trace->terminals();
        $result = (new OperatorArgumentsRule())->rewrite($input);
        self::assertSame(['(', 'NONE', ',', 'NUMERIC', '(', 'ICONST', ',', 'ICONST', ')', ')'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, (new OperatorArgumentsRule())->rewrite($result));
    }

    public function testHasArgumentSeparatorDistinguishesNestedTypeModifiers(): void
    {
        $rule = new OperatorArgumentsRule();
        $nested = TerminalSequence::fromNames(['(', 'NUMERIC', '(', 'ICONST', ',', 'ICONST', ')', ')']);
        self::assertFalse($rule->hasArgumentSeparator($nested, 0, 8));
        $binary = TerminalSequence::fromNames(['(', 'NONE', ',', 'INT', ')']);
        self::assertTrue($rule->hasArgumentSeparator($binary, 0, 5));
    }
}
