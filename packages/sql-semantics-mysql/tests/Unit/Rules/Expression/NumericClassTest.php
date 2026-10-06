<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Expression\NumericClass;

#[CoversClass(NumericClass::class)]
#[Small]
final class NumericClassTest extends TestCase
{
    public function testCasesAreTheFourArithmetics(): void
    {
        self::assertSame(['Signed', 'Unsigned', 'Decimal', 'Double'], array_column(NumericClass::cases(), 'name'));
    }
}
