<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Contract;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Contract\Production;
use SqlFaker\Contract\ProductionRule;
use SqlFaker\Contract\Symbol;

#[CoversClass(ProductionRule::class)]
#[UsesClass(Production::class)]
#[UsesClass(Symbol::class)]
final class ProductionRuleTest extends TestCase
{
    public function testConstructsReadonlyProductionRule(): void
    {
        $rule = new ProductionRule('stmt', [
            new Production([new Symbol('SELECT', false)]),
        ]);

        self::assertSame('stmt', $rule->name);
        self::assertCount(1, $rule->alternatives);
    }

    public function testRejectsEmptyRuleName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production rule name must be non-empty.');

        new ProductionRule('', []);
    }

    public function testRejectsNonListAlternatives(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Production rule alternatives must be a list.');

        $alternatives = ['x' => new Production([])];

        new ProductionRule('stmt', $alternatives);
    }
}
