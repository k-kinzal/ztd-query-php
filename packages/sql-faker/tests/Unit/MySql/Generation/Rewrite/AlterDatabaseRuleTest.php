<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\MySql\Generation\Rewrite\AlterDatabaseRule;

#[CoversClass(AlterDatabaseRule::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalSequence::class)]
final class AlterDatabaseRuleTest extends TestCase
{
    #[DataProvider('providerOptions')]
    public function testRewriteAddsTheOptionIntroducerOnlyForAnOmittedDatabase(string $option): void
    {
        $trace = new DerivationTrace('alter_database_stmt');
        $trace->expand(0, new Production([new Terminal('ALTER'), new Terminal('DATABASE'), new NonTerminal('ident_or_empty'), new Terminal($option)]), 0);
        $trace->expand(2, new Production([]), 0);
        $input = $trace->terminals();
        self::assertSame(['ALTER', 'DATABASE', 'DEFAULT_SYM', $option], (new AlterDatabaseRule())->rewrite($input)->names());
    }

    public function testRewriteKeepsAnExplicitDatabaseNamedEncryption(): void
    {
        $trace = new DerivationTrace('alter_database_stmt');
        $trace->expand(0, new Production([new Terminal('ALTER'), new Terminal('DATABASE'), new NonTerminal('ident_or_empty'), new Terminal('ENCRYPTION_SYM')]), 0);
        $trace->expand(2, new Production([new Terminal('ENCRYPTION_SYM')]), 1);
        $input = $trace->terminals();
        self::assertSame($input, (new AlterDatabaseRule())->rewrite($input));
    }

    public function testRewriteUsesTheConfiguredDefaultToken(): void
    {
        $trace = new DerivationTrace('alter_database_stmt');
        $trace->expand(0, new Production([new Terminal('ALTER'), new Terminal('DATABASE'), new NonTerminal('ident_or_empty'), new Terminal('CHARSET')]), 0);
        $trace->expand(2, new Production([]), 0);
        self::assertSame(['ALTER', 'DATABASE', 'DEFAULT', 'CHARSET'], (new AlterDatabaseRule('DEFAULT'))->rewrite($trace->terminals())->names());
    }
    /**
     * @return list<array{string}>
     */
    public static function providerOptions(): array
    {
        return [['ENCRYPTION_SYM'], ['CHARSET'], ['CHAR_SYM'], ['COLLATE_SYM']];
    }
}
