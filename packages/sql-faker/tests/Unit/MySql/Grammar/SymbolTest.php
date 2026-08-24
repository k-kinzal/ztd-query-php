<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Symbol;
use SqlFaker\MySql\Grammar\Terminal;

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
}
