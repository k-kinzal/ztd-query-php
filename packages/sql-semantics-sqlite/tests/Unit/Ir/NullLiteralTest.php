<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;

#[CoversClass(NullLiteral::class)]
#[Small]
final class NullLiteralTest extends TestCase
{
    public function testRejectsClone(): void
    {
        $this->expectExceptionMessage('cannot be cloned');

        $value = new NullLiteral();
        self::assertNotSame($value, clone $value);
    }
}
