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
use SqlFaker\MySql\Generation\Rewrite\AlterDatabaseRule;

#[CoversClass(AlterDatabaseRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
final class AlterDatabaseRuleTest extends TestCase
{
    public function testRewriteAddsTheOptionIntroducerOnlyForAnOmittedDatabase(): void
    {
        $trace = new DerivationTrace('alter_database_stmt');
        $trace->expand(0, new Production([new Terminal('ALTER'), new Terminal('DATABASE'), new NonTerminal('ident_or_empty'), new Terminal('ENCRYPTION_SYM')]), 0);
        $trace->expand(2, new Production([]), 0);
        $input = $trace->terminals();
        self::assertSame(['ALTER', 'DATABASE', 'DEFAULT_SYM', 'ENCRYPTION_SYM'], (new AlterDatabaseRule())->rewrite($input)->names());
    }

    public function testRewriteKeepsAnExplicitDatabaseNamedEncryption(): void
    {
        $trace = new DerivationTrace('alter_database_stmt');
        $trace->expand(0, new Production([new Terminal('ALTER'), new Terminal('DATABASE'), new NonTerminal('ident_or_empty'), new Terminal('ENCRYPTION_SYM')]), 0);
        $trace->expand(2, new Production([new Terminal('ENCRYPTION_SYM')]), 1);
        $input = $trace->terminals();
        self::assertSame($input, (new AlterDatabaseRule())->rewrite($input));
    }
}
