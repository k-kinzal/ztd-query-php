<?php

declare(strict_types=1);

namespace Tests\Unit\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Grammar\Rule;

#[CoversClass(Rule::class)]
#[Small]
final class RuleTest extends TestCase
{
    public function testLength(): void
    {
        self::assertSame(3, (new Rule(1, 5, [1, 2, 3], 0))->length());
        self::assertSame(0, (new Rule(2, 5, [], 1, true, 7))->length());
    }

    public function testLengthKeepsTheDeclaredProperties(): void
    {
        $rule = new Rule(2, 5, [], 1, true, 7);

        self::assertSame(2, $rule->index);
        self::assertSame(5, $rule->lhs);
        self::assertTrue($rule->hidden);
        self::assertSame(7, $rule->precedenceSymbol);
        self::assertSame(1, $rule->ordinal);
    }
}
