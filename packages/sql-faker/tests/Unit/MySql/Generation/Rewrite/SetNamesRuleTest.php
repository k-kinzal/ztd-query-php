<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\Rewrite\SetNamesRule;

#[CoversClass(SetNamesRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class SetNamesRuleTest extends TestCase
{
    public function testRewriteRepairsColonEqualsWithoutChangingOtherAssignments(): void
    {
        $trace = new DerivationTrace('option_value_no_option_type');
        $trace->expand(0, new Production([new Terminal('NAMES_SYM'), new Terminal('SET_VAR'), new Terminal('NUM')]), 0);
        $input = $trace->terminals();
        $result = (new SetNamesRule())->rewrite($input);
        self::assertSame(['NAMES_SYM', 'DEFAULT_SYM'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertSame($input->productions, $result->productions);
        self::assertSame($result, (new SetNamesRule())->rewrite($result));

        $ordinary = new DerivationTrace('option_value_no_option_type');
        $ordinary->expand(0, new Production([new Terminal('IDENT'), new Terminal('SET_VAR'), new Terminal('NUM')]), 0);
        $assignment = $ordinary->terminals();
        self::assertSame($assignment, (new SetNamesRule())->rewrite($assignment));
    }

    public function testRewriteUsesTheConfiguredDefaultToken(): void
    {
        $trace = new DerivationTrace('option_value_no_option_type');
        $trace->expand(0, new Production([new Terminal('NAMES_SYM'), new Terminal('EQ'), new Terminal('NUM')]), 0);
        self::assertSame(['NAMES_SYM', 'DEFAULT'], (new SetNamesRule('DEFAULT'))->rewrite($trace->terminals())->names());
    }

    public function testRewriteReplacesTheEntireErrorProductionWithoutTouchingAdjacentOptions(): void
    {
        $trace = new DerivationTrace('root');
        $trace->expand(0, new Production([new NonTerminal('option_value_no_option_type'), new Terminal(','), new Terminal('IDENT'), new Terminal('EQ'), new Terminal('NUM')]), 0);
        $trace->expand(0, new Production([new Terminal('NAMES_SYM'), new Terminal('EQ'), new Terminal('COMPLEX'), new Terminal('EXPR')]), 0);
        $input = $trace->terminals();
        $result = (new SetNamesRule())->rewrite($input);
        self::assertSame(['NAMES_SYM', 'DEFAULT_SYM', ',', 'IDENT', 'EQ', 'NUM'], $result->names());
        self::assertSame($input->original, $result->original);
        self::assertCount(count($result->terminals), array_unique(array_map(static fn ($terminal): int => $terminal->id, $result->terminals)));
        self::assertSame($result, (new SetNamesRule())->rewrite($result));
    }
}
