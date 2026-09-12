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
use SqlFaker\MySql\Generation\Rewrite\InstanceActionRule;

#[CoversClass(InstanceActionRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class InstanceActionRuleTest extends TestCase
{
    public function testRewriteRecognizesTheTwoCheckedRedoNamesButLeavesReloadChannelsAlone(): void
    {
        $trace = new DerivationTrace('alter_instance_action');
        $trace->expand(0, new Production([new Terminal('ENABLE_SYM'), new NonTerminal('ident'), new NonTerminal('ident')]), 5);
        $trace->expand(1, new Production([new Terminal('IDENT')]), 0);
        $trace->expand(2, new Production([new Terminal('IDENT')]), 0);
        $input = $trace->terminals();
        self::assertSame(['ENABLE_SYM', 'REDO_ENGINE', 'REDO_LOG_NAME'], (new InstanceActionRule())->rewrite($input)->names());
        $reload = $input->replace(0, 1, [$input->terminals[0]->replaced('RELOAD', 'test')], 'test');
        self::assertSame($reload, (new InstanceActionRule())->rewrite($reload));
    }

    public function testRewriteResolvesTheMasterKeyEngineAsAContextualDomain(): void
    {
        $trace = new DerivationTrace('alter_instance_action');
        $trace->expand(0, new Production([new Terminal('ROTATE_SYM'), new NonTerminal('ident_or_text'), new Terminal('MASTER_SYM'), new Terminal('KEY_SYM')]), 0);
        $trace->expand(1, new Production([new Terminal('LEX_HOSTNAME')]), 1);
        self::assertSame(['ROTATE_SYM', 'ROTATE_KEY_ENGINE', 'MASTER_SYM', 'KEY_SYM'], (new InstanceActionRule())->rewrite($trace->terminals())->names());
    }
}
