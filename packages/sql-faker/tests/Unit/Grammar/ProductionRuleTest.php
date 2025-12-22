<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
final class ProductionRuleTest extends TestCase
{
    public function testConstructor(): void
    {
        $alternatives = [new Production([new Terminal('A')])];
        $rule = new ProductionRule('expr', $alternatives);

        self::assertSame('expr', $rule->lhs);
        self::assertSame($alternatives, $rule->alternatives);
    }

    public function testLhs(): void
    {
        $rule = new ProductionRule('stmt', []);

        self::assertSame('stmt', $rule->lhs);
    }

    public function testAlternatives(): void
    {
        $alt1 = new Production([new Terminal('A')]);
        $alt2 = new Production([new Terminal('B')]);
        $rule = new ProductionRule('expr', [$alt1, $alt2]);

        self::assertSame([$alt1, $alt2], $rule->alternatives);
    }
}
