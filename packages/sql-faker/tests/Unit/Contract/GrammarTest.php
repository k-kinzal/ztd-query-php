<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Grammar;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;

#[CoversClass(Grammar::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Production::class)]
#[UsesClass(Symbol::class)]
final class GrammarTest extends TestCase
{
    public function testConstructsReadonlyGrammarAndLooksUpRules(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Symbol('SELECT', false)]),
            ]),
        ]);

        self::assertSame('stmt', $grammar->startSymbol);
        self::assertNotNull($grammar->rule('stmt'));
        self::assertNull($grammar->rule('missing'));
    }

    public function testConvertsInternalGrammarObjectsIntoContractGrammar(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $grammar = Grammar::from((object) [
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
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar source must expose a non-empty startSymbol string.');

        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        Grammar::from((object) ['startSymbol' => '', 'ruleMap' => []], $nonTerminal::class);
    }

    public function testRejectsNonStringRuleKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar rules must be keyed by non-empty strings.');

        new Grammar('stmt', [
            0 => new ProductionRule('stmt', []),
        ]);
    }

    public function testRejectsNonProductionRuleValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar rules must contain only ProductionRule values.');

        new Grammar('stmt', [
            'stmt' => new \stdClass(),
        ]);
    }

    public function testRejectsNonObjectRuleMapEntriesWhenConvertingGrammarSource(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar source ruleMap must be an object map keyed by strings.');

        Grammar::from((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                'stmt' => 'invalid',
            ],
        ], $nonTerminal::class);
    }

    public function testRejectsAssociativeAlternativeListsWhenConvertingGrammarSource(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production rule source must expose an alternatives list.');

        Grammar::from((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                'stmt' => (object) [
                    'alternatives' => [
                        'named' => (object) ['symbols' => []],
                    ],
                ],
            ],
        ], $nonTerminal::class);
    }

    public function testRejectsAssociativeSymbolListsWhenConvertingGrammarSource(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production source must expose a symbols list.');

        Grammar::from((object) [
            'startSymbol' => 'stmt',
            'ruleMap' => [
                'stmt' => (object) [
                    'alternatives' => [
                        (object) [
                            'symbols' => [
                                'named' => (object) ['value' => 'SELECT'],
                            ],
                        ],
                    ],
                ],
            ],
        ], $nonTerminal::class);
    }

    public function testRejectsEmptySymbolValuesWhenConvertingGrammarSource(): void
    {
        $nonTerminal = new class ('expr') {
            public function __construct(public string $value)
            {
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Grammar symbol source must expose a non-empty value string.');

        Grammar::from((object) [
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
