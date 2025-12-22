<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonAst;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonSymbolNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenInfo;
use SqlFaker\MySql\Grammar\GrammarCompiler;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\UnknownSymbolException;

final class GrammarCompilerTest extends TestCase
{
    public function testCompile(): void
    {
        $ast = new BisonAst(
            startSymbol: 'start',
            prologue: null,
            declarations: [],
            rules: [
                new BisonRuleNode('start', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'expr')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('expr', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertSame('start', $grammar->startSymbol);
        self::assertCount(2, $grammar->ruleMap);
        self::assertArrayHasKey('start', $grammar->ruleMap);
        self::assertArrayHasKey('expr', $grammar->ruleMap);
    }

    public function testCompileWithMultipleAlternatives(): void
    {
        $ast = new BisonAst(
            startSymbol: 'expr',
            prologue: null,
            declarations: [
                new BisonTokenDeclaration(null, [
                    new BisonTokenInfo('NUM', null, null),
                ]),
            ],
            rules: [
                new BisonRuleNode('expr', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'NUM')],
                        null,
                        null,
                        null,
                        null
                    ),
                    new BisonAlternativeNode(
                        [
                            new BisonSymbolNode(BisonSymbolType::Identifier, 'expr'),
                            new BisonSymbolNode(BisonSymbolType::CharLiteral, '+'),
                            new BisonSymbolNode(BisonSymbolType::Identifier, 'expr'),
                        ],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertCount(2, $grammar->ruleMap['expr']->alternatives);

        $firstAlt = $grammar->ruleMap['expr']->alternatives[0];
        self::assertCount(1, $firstAlt->symbols);

        $secondAlt = $grammar->ruleMap['expr']->alternatives[1];
        self::assertCount(3, $secondAlt->symbols);
        self::assertInstanceOf(Terminal::class, $secondAlt->symbols[1]);
        self::assertSame('+', $secondAlt->symbols[1]->value);
    }

    public function testCompileMergesSameNameRules(): void
    {
        $ast = new BisonAst(
            startSymbol: 'stmt',
            prologue: null,
            declarations: [],
            rules: [
                new BisonRuleNode('stmt', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'select')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('stmt', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'insert')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('select', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
                new BisonRuleNode('insert', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertArrayHasKey('stmt', $grammar->ruleMap);
        self::assertCount(2, $grammar->ruleMap['stmt']->alternatives);
    }

    public function testCompileWithEmptyProduction(): void
    {
        $ast = new BisonAst(
            startSymbol: 'opt',
            prologue: null,
            declarations: [],
            rules: [
                new BisonRuleNode('opt', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertCount(1, $grammar->ruleMap['opt']->alternatives);
        self::assertSame([], $grammar->ruleMap['opt']->alternatives[0]->symbols);
    }

    public function testCompileIgnoresNonTokenDeclarations(): void
    {
        $ast = new BisonAst(
            startSymbol: 'start',
            prologue: null,
            declarations: [
                new BisonStartDeclaration('start'),
            ],
            rules: [
                new BisonRuleNode('start', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'expr')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('expr', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertCount(2, $grammar->ruleMap);
    }

    public function testCompileDistinguishesTerminalsAndNonTerminals(): void
    {
        $ast = new BisonAst(
            startSymbol: 'list',
            prologue: null,
            declarations: [
                new BisonTokenDeclaration(null, [
                    new BisonTokenInfo('TOKEN', null, null),
                ]),
            ],
            rules: [
                new BisonRuleNode('list', [
                    new BisonAlternativeNode(
                        [
                            new BisonSymbolNode(BisonSymbolType::Identifier, 'item'),
                            new BisonSymbolNode(BisonSymbolType::CharLiteral, ','),
                            new BisonSymbolNode(BisonSymbolType::Identifier, 'item'),
                        ],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('item', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'TOKEN')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        $symbols = $grammar->ruleMap['list']->alternatives[0]->symbols;

        // 'item' is a rule name, so it's a NonTerminal
        self::assertInstanceOf(NonTerminal::class, $symbols[0]);
        self::assertSame('item', $symbols[0]->value);

        // ',' is a char literal, always a Terminal
        self::assertInstanceOf(Terminal::class, $symbols[1]);
        self::assertSame(',', $symbols[1]->value);

        // 'item' again is NonTerminal
        self::assertInstanceOf(NonTerminal::class, $symbols[2]);

        // 'TOKEN' is declared as token, so it's a Terminal
        $itemSymbols = $grammar->ruleMap['item']->alternatives[0]->symbols;
        self::assertInstanceOf(Terminal::class, $itemSymbols[0]);
        self::assertSame('TOKEN', $itemSymbols[0]->value);
    }

    public function testCompileUsesStartSymbolFromAst(): void
    {
        $ast = new BisonAst(
            startSymbol: 'my_custom_start',
            prologue: null,
            declarations: [],
            rules: [
                new BisonRuleNode('my_custom_start', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertSame('my_custom_start', $grammar->startSymbol);
    }

    public function testCompileDiscardsActionAndPrecedence(): void
    {
        $ast = new BisonAst(
            startSymbol: 'expr',
            prologue: null,
            declarations: [
                new BisonTokenDeclaration(null, [
                    new BisonTokenInfo('NUM', null, null),
                ]),
            ],
            rules: [
                new BisonRuleNode('expr', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'NUM')],
                        '{ $$ = $1; }',  // action - should be discarded
                        'UMINUS',         // prec - should be discarded
                        1,                // dprec - should be discarded
                        '<merge_func>'    // merge - should be discarded
                    ),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        // Grammar should still compile successfully
        self::assertCount(1, $grammar->ruleMap);
        self::assertCount(1, $grammar->ruleMap['expr']->alternatives);

        // Production only contains symbols, no action/precedence info
        $production = $grammar->ruleMap['expr']->alternatives[0];
        self::assertCount(1, $production->symbols);
        self::assertInstanceOf(Terminal::class, $production->symbols[0]);
        self::assertSame('NUM', $production->symbols[0]->value);
    }

    public function testCompileWithMultipleDistinctRules(): void
    {
        $ast = new BisonAst(
            startSymbol: 'program',
            prologue: null,
            declarations: [],
            rules: [
                new BisonRuleNode('program', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'stmt_list')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('stmt_list', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'stmt')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('stmt', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'expr')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('expr', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertCount(4, $grammar->ruleMap);
        self::assertArrayHasKey('program', $grammar->ruleMap);
        self::assertArrayHasKey('stmt_list', $grammar->ruleMap);
        self::assertArrayHasKey('stmt', $grammar->ruleMap);
        self::assertArrayHasKey('expr', $grammar->ruleMap);
    }

    public function testCompileWithEmptyDeclarationsAndSingleRule(): void
    {
        $ast = new BisonAst(
            startSymbol: 'start',
            prologue: null,
            declarations: [],
            rules: [
                new BisonRuleNode('start', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        self::assertCount(1, $grammar->ruleMap);
    }

    public function testCompileIgnoresPrologueAndEpilogue(): void
    {
        $prologue = '%{ #include <stdio.h> %}';
        $epilogue = 'int main() { return 0; }';

        $ast = new BisonAst(
            startSymbol: 'start',
            prologue: $prologue,
            declarations: [],
            rules: [
                new BisonRuleNode('start', [
                    new BisonAlternativeNode([], null, null, null, null),
                ]),
            ],
            epilogue: $epilogue
        );

        $compiler = new GrammarCompiler();
        $grammar = $compiler->compile($ast);

        // Grammar should compile without prologue/epilogue affecting it
        self::assertSame('start', $grammar->startSymbol);
        self::assertCount(1, $grammar->ruleMap);
    }

    public function testCompileThrowsOnUnknownSymbol(): void
    {
        $ast = new BisonAst(
            startSymbol: 'start',
            prologue: null,
            declarations: [],
            rules: [
                new BisonRuleNode('start', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolType::Identifier, 'UNKNOWN')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
            ],
            epilogue: null
        );

        $compiler = new GrammarCompiler();

        $this->expectException(UnknownSymbolException::class);
        $this->expectExceptionMessage('Unknown symbol: UNKNOWN');

        $compiler->compile($ast);
    }
}
