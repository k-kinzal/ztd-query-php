<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;

#[CoversClass(RangeIntegerLiteral::class)]
#[Small]
final class RangeIntegerLiteralTest extends TestCase
{
    public function testToStringPreservesSignAndMagnitude(): void
    {
        self::assertSame('-009223372036854775808', (new RangeIntegerLiteral('-9223372036854775808', 2))->toString());
        self::assertSame('-000', (new RangeIntegerLiteral('0', 2, true))->toString());
    }
}
