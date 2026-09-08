<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Generation\Rewrite\SetNamesRule;

#[CoversClass(SetNamesRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class SetNamesRuleTest extends TestCase
{
    public function testRewriteReplacesTheEntireErrorProductionWithoutTouchingAdjacentOptions(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('option_value_no_option_type'), new Terminal(','), new Terminal('IDENT'), new Terminal('EQ'), new Terminal('NUM')]), 0);
        $trace->expand(0, new Production([new Terminal('NAMES_SYM'), new Terminal('EQ'), new Terminal('COMPLEX'), new Terminal('EXPR')]), 0);
        $input = $trace->terminals();
        $result = (new SetNamesRule())->rewrite($input);
        self::assertSame(['NAMES_SYM', 'DEFAULT_SYM', ',', 'IDENT', 'EQ', 'NUM'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($result, (new SetNamesRule())->rewrite($result));
    }
}
