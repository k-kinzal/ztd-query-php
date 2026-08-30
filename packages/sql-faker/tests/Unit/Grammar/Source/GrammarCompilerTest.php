<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Model\UnknownSymbolException;
use SqlFaker\Grammar\Source\Bison\Ast\BisonAlternativeNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonAst;
use SqlFaker\Grammar\Source\Bison\Ast\BisonRuleNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolForm;
use SqlFaker\Grammar\Source\Bison\Ast\BisonSymbolNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonTokenDefinition;
use SqlFaker\Grammar\Source\GrammarCompiler;

#[CoversClass(GrammarCompiler::class)]
#[CoversClass(BisonAst::class)]
#[CoversClass(BisonRuleNode::class)]
#[CoversClass(BisonAlternativeNode::class)]
#[CoversClass(BisonSymbolNode::class)]
#[CoversClass(BisonTokenDeclaration::class)]
#[CoversClass(BisonTokenDefinition::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(UnknownSymbolException::class)]
#[CoversClass(\SqlFaker\Grammar\Model\Grammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(\SqlFaker\Grammar\Model\Production::class)]
#[CoversClass(\SqlFaker\Grammar\Model\ProductionRule::class)]
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
                        [new BisonSymbolNode(BisonSymbolForm::Identifier, 'expr')],
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
                    new BisonTokenDefinition('NUM', null, null),
                ]),
            ],
            rules: [
                new BisonRuleNode('expr', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolForm::Identifier, 'NUM')],
                        null,
                        null,
                        null,
                        null
                    ),
                    new BisonAlternativeNode(
                        [
                            new BisonSymbolNode(BisonSymbolForm::Identifier, 'expr'),
                            new BisonSymbolNode(BisonSymbolForm::CharLiteral, '+'),
                            new BisonSymbolNode(BisonSymbolForm::Identifier, 'expr'),
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
                        [new BisonSymbolNode(BisonSymbolForm::Identifier, 'select')],
                        null,
                        null,
                        null,
                        null
                    ),
                ]),
                new BisonRuleNode('stmt', [
                    new BisonAlternativeNode(
                        [new BisonSymbolNode(BisonSymbolForm::Identifier, 'insert')],
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
                        [new BisonSymbolNode(BisonSymbolForm::Identifier, 'UNKNOWN')],
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

    public function testRuleTableAnswersEveryNameTheGrammarDefinesARuleFor(): void
    {
        $ast = new BisonAst(
            startSymbol: 'start',
            prologue: null,
            declarations: [],
            rules: [new BisonRuleNode('start', []), new BisonRuleNode('tail', [])],
            epilogue: null,
        );

        self::assertSame(['start', 'tail'], array_keys((new GrammarCompiler())->ruleTable($ast)));
    }

    public function testDeclarationTableAnswersEveryNameTheGrammarDeclaresAsAToken(): void
    {
        $ast = new BisonAst(
            startSymbol: 'start',
            prologue: null,
            declarations: [new BisonTokenDeclaration(null, [new BisonTokenDefinition('WORD', null, null)])],
            rules: [],
            epilogue: null,
        );

        self::assertSame(['WORD'], array_keys((new GrammarCompiler())->declarationTable($ast)));
    }

    public function testSymbolsReadsANameWithARuleAsANonTerminal(): void
    {
        $symbols = (new GrammarCompiler())->symbols(
            [new BisonSymbolNode(BisonSymbolForm::Identifier, 'tail')],
            ['tail' => new BisonRuleNode('tail', [])],
            [],
        );

        self::assertInstanceOf(NonTerminal::class, $symbols[0]);
        self::assertSame('tail', $symbols[0]->value);
    }

    public function testSymbolsRefusesANameTheGrammarNeverDeclares(): void
    {
        $this->expectException(UnknownSymbolException::class);

        (new GrammarCompiler())->symbols([new BisonSymbolNode(BisonSymbolForm::Identifier, 'gone')], [], []);
    }
}
