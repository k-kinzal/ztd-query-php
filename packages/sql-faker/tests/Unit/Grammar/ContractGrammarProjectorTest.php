<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;
use SqlFaker\Grammar\ContractGrammarProjector;

#[CoversClass(ContractGrammarProjector::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Production::class)]
#[UsesClass(Symbol::class)]
final class ContractGrammarProjectorTest extends TestCase
{
    public function testProjectsInternalGrammarObjectsIntoContractGrammar(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $grammar = ContractGrammarProjector::project((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                'stmt' => (object) [
                    'alternatives' => [
                        (object) [
                            'symbols' => [
                                (object) ['value' => 'SELECT'],
                                $nonTerminal,
                            ],
                        ],
                    ],
                ],
            ],
        ], $nonTerminal::class);

        self::assertSame('stmt', $grammar->startSymbol);
        self::assertSame(['t:SELECT', 'nt:expr'], $grammar->rule('stmt')?->alternatives[0]->sequence());
    }

    public function testRejectsInvalidGrammarSource(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar source must expose a non-empty startSymbol string.');

        ContractGrammarProjector::project((object) ['startSymbol' => '', 'ruleMap' => []], $nonTerminal::class);
    }

    public function testRejectsRuleMapsThatAreNotObjectMaps(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar source ruleMap must be an object map keyed by strings.');

        ContractGrammarProjector::project((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                0 => (object) ['alternatives' => []],
            ],
        ], $nonTerminal::class);
    }

    public function testRejectsRuleSourcesWithoutAlternativeLists(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production rule source must expose an alternatives list.');

        ContractGrammarProjector::project((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                'stmt' => (object) ['alternatives' => (object) []],
            ],
        ], $nonTerminal::class);
    }

    public function testRejectsProductionSourcesWithoutSymbolLists(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production source must expose a symbols list.');

        ContractGrammarProjector::project((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                'stmt' => (object) [
                    'alternatives' => [
                        (object) ['symbols' => (object) []],
                    ],
                ],
            ],
        ], $nonTerminal::class);
    }

    public function testRejectsSymbolsWithoutNonEmptyValueStrings(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar symbol source must expose a non-empty value string.');

        ContractGrammarProjector::project((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                'stmt' => (object) [
                    'alternatives' => [
                        (object) [
                            'symbols' => [
                                (object) ['value' => ''],
                            ],
                        ],
                    ],
                ],
            ],
        ], $nonTerminal::class);
    }
}
