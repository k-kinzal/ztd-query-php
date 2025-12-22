<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Ast;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Bison\Ast\BisonAlternativeNode;
use SqlFaker\MySql\Bison\Ast\BisonRuleNode;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(BisonRuleNode::class)]
#[CoversClass(BisonAlternativeNode::class)]
final class BisonRuleNodeTest extends TestCase
{
    public function testName(): void
    {
        $rule = new BisonRuleNode('select_stmt', []);

        self::assertSame('select_stmt', $rule->name);
    }

    public function testAlternatives(): void
    {
        $alt1 = new BisonAlternativeNode([], null, null, null, null);
        $alt2 = new BisonAlternativeNode([], 'action', null, null, null);
        $rule = new BisonRuleNode('expr', [$alt1, $alt2]);

        self::assertSame([$alt1, $alt2], $rule->alternatives);
    }
}
