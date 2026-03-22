<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\MySql\Grammar\ContractGrammarHydrator;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Terminal;

#[CoversNothing]
final class ContractGrammarHydratorTest extends TestCase
{
    public function testHydrateReconstructsMySqlGrammarModelFromContractGrammar(): void
    {
        $grammar = ContractGrammarHydrator::hydrate(new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([
                    new Symbol('SELECT_SYM', false),
                    new Symbol('expr', true),
                ]),
            ]),
            'expr' => new ProductionRule('expr', [
                new Production([new Symbol('NUM', false)]),
            ]),
        ]));

        self::assertSame('stmt', $grammar->startSymbol);
        self::assertInstanceOf(Terminal::class, $grammar->ruleMap['stmt']->alternatives[0]->symbols[0]);
        self::assertInstanceOf(NonTerminal::class, $grammar->ruleMap['stmt']->alternatives[0]->symbols[1]);
    }
}
