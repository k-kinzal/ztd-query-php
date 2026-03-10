<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GrammarCompiler;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\UnknownSymbolException;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonAst;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolNode;
use SqlFaker\MySql\Bison\Ast\BisonSymbolType;
use SqlFaker\MySql\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\MySql\Bison\Ast\BisonTokenInfo;

#[CoversNothing]
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

    public function testCompileMultipleAlternatives(): void
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
        self::assertCount(1, $grammar->ruleMap['expr']->alternatives[0]->symbols);
        self::assertCount(3, $grammar->ruleMap['expr']->alternatives[1]->symbols);
        self::assertInstanceOf(Terminal::class, $grammar->ruleMap['expr']->alternatives[1]->symbols[1]);
        self::assertSame('+', $grammar->ruleMap['expr']->alternatives[1]->symbols[1]->value);
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
