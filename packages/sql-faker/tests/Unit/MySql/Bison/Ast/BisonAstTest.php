<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonAst;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use SqlFaker\MySql\Bison\Ast\BisonStartDeclaration;

#[CoversNothing]
final class BisonAstTest extends TestCase
{
    public function testConstructor(): void
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

    public function testConstructorWithNulls(): void
    {
        $ast = new BisonAst('start', null, [], [], null);

        self::assertSame('start', $ast->startSymbol);
        self::assertNull($ast->prologue);
        self::assertSame([], $ast->declarations);
        self::assertSame([], $ast->rules);
        self::assertNull($ast->epilogue);
    }
}
