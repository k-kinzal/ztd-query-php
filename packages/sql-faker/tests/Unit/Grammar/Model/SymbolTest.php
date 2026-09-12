<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Model;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Symbol;
use SqlFaker\Grammar\Model\Terminal;

#[CoversNothing]
final class SymbolTest extends TestCase
{
    public function testEveryImplementationReportsItsOwnValue(): void
    {
        $symbols = [new Terminal('SELECT_SYM'), new NonTerminal('statement')];

        self::assertSame(
            ['SELECT_SYM', 'statement'],
            array_map(static fn (Symbol $symbol): string => $symbol->value(), $symbols),
        );
    }

    public function testValueAnswersTheNameTheGrammarSpells(): void
    {
        self::assertSame('SELECT', (new Terminal('SELECT'))->value());
        self::assertSame('expr', (new NonTerminal('expr'))->value());
    }
}
