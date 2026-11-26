<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Predicate\Text\TextOperator;

#[CoversClass(TextOperator::class)]
#[Small]
final class TextOperatorTest extends TestCase
{
    public function testNullSafeEqualityHasItsDistinctNativeOperator(): void
    {
        self::assertSame('<=>', TextOperator::NullSafeEqual->value);
    }
}
