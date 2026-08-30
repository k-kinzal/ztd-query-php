<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Ast\BisonExpectDeclaration;
use SqlFaker\Grammar\Source\Bison\Ast\BisonRuleNode;
use SqlFaker\Grammar\Source\Bison\Ast\BisonStartDeclaration;
use SqlFaker\Grammar\Source\Bison\BisonStartSymbol;

#[CoversClass(BisonStartSymbol::class)]
#[UsesClass(BisonExpectDeclaration::class)]
#[UsesClass(BisonRuleNode::class)]
#[UsesClass(BisonStartDeclaration::class)]
final class BisonStartSymbolTest extends TestCase
{
    public function testFromPrefersTheDeclaredStartRule(): void
    {
        $symbol = (new BisonStartSymbol())->from(
            [new BisonStartDeclaration('statement')],
            [new BisonRuleNode('first_rule', [])],
        );

        self::assertSame('statement', $symbol);
    }

    public function testFromFallsBackToTheFirstRuleWhenNoneIsDeclared(): void
    {
        $symbol = (new BisonStartSymbol())->from(
            [new BisonExpectDeclaration(3)],
            [new BisonRuleNode('first_rule', []), new BisonRuleNode('second_rule', [])],
        );

        self::assertSame('first_rule', $symbol);
    }

    public function testFromTakesTheFirstStartDeclarationWhenSeveralAreWritten(): void
    {
        $symbol = (new BisonStartSymbol())->from(
            [new BisonStartDeclaration('first'), new BisonStartDeclaration('second')],
            [new BisonRuleNode('rule', [])],
        );

        self::assertSame('first', $symbol);
    }
}
