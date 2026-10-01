<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Identifier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Identifier\Comparison;

#[CoversClass(Comparison::class)]
#[Small]
final class ComparisonTest extends TestCase
{
    #[TestWith([Comparison::Sensitive, 'Foo', 'foo', false])]
    #[TestWith([Comparison::Sensitive, 'foo', 'foo', true])]
    #[TestWith([Comparison::AsciiInsensitive, 'Foo', 'fOO', true])]
    #[TestWith([Comparison::AsciiInsensitive, 'Ä', 'ä', false])]
    #[TestWith([Comparison::AsciiInsensitive, 'foo', 'bar', false])]
    public function testEqualUsesTheNamespacePolicy(Comparison $policy, string $left, string $right, bool $expected): void
    {
        self::assertSame($expected, $policy->equal($left, $right));
        self::assertSame($expected, $policy->equal($right, $left));
    }
}
