<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;

#[CoversClass(WideIntegerLiteral::class)]
#[Small]
final class WideIntegerLiteralTest extends TestCase
{
    public function testHoldsBothExactEndpoints(): void
    {
        self::assertSame('9223372036854775807', (new WideIntegerLiteral('9223372036854775807'))->decimal);
        self::assertSame('-9223372036854775808', (new WideIntegerLiteral('-9223372036854775808'))->decimal);
    }

    public function testRejectsTheSuccessorOfTheUpperEndpoint(): void
    {
        $this->expectExceptionMessage('outside the signed 64-bit range');

        new WideIntegerLiteral('9223372036854775808');
    }
}
