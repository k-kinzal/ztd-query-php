<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Literal\ProjectedIntegerLiteral;

#[CoversClass(ProjectedIntegerLiteral::class)]
#[Small]
final class ProjectedIntegerLiteralTest extends TestCase
{
    public function testDecimalResolvesTheExactSignedValue(): void
    {
        self::assertSame('-9223372036854775808', (new ProjectedIntegerLiteral('-0009223372036854775808'))->decimal());
        self::assertSame('0', (new ProjectedIntegerLiteral('-000'))->decimal());
    }

    public function testToStringRetainsTheNativeDigitExtent(): void
    {
        $token = '-' . str_repeat('0', 1024) . '22';

        self::assertSame($token, (new ProjectedIntegerLiteral($token))->toString());
    }
}
