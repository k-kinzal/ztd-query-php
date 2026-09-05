<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Ast;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Ast\BisonAlternativeNode;
use SqlFaker\Compiler\Bison\Ast\BisonAst;
use SqlFaker\Compiler\Bison\Ast\BisonRuleNode;
use SqlFaker\Compiler\Bison\Ast\BisonStartDeclaration;

#[CoversClass(BisonAst::class)]
#[CoversClass(BisonStartDeclaration::class)]
#[CoversClass(BisonRuleNode::class)]
#[CoversClass(BisonAlternativeNode::class)]
final class BisonAstTest extends TestCase
{
    public function testExposesEveryPartOfTheGrammarFile(): void
    {
        $declaration = new BisonStartDeclaration('start');
        $rule = new BisonRuleNode('start', [
            new BisonAlternativeNode([], null, null, null, null),
        ]);

        $ast = new BisonAst('start', '%{ code %}', [$declaration], [$rule], 'epilogue');

        self::assertSame('start', $ast->startSymbol);
        self::assertSame('%{ code %}', $ast->prologue);
        self::assertSame([$declaration], $ast->declarations);
        self::assertSame([$rule], $ast->rules);
        self::assertSame('epilogue', $ast->epilogue);
    }

    public function testExposesOmittedPartsAsNull(): void
    {
        $ast = new BisonAst('start', null, [], [], null);

        self::assertSame('start', $ast->startSymbol);
        self::assertNull($ast->prologue);
        self::assertSame([], $ast->declarations);
        self::assertSame([], $ast->rules);
        self::assertNull($ast->epilogue);
    }
}
