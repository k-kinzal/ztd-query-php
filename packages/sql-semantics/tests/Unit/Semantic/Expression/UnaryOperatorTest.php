<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Expression;

use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Expression\UnaryOperator::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class UnaryOperatorTest extends TestCase
{
    public function testNullTestsAreNotBinaryComparisons(): void
    {
        self::assertSame('IS NULL', \SqlSemantics\Semantic\Expression\UnaryOperator::IsNull->value);
    }
}
