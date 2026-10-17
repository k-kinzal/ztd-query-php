<?php

declare(strict_types=1);

namespace Tests\Unit\Dictionary;

use MySqlMemory\Dictionary\Fill;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Fill::class)]
#[Small]
final class FillTest extends TestCase
{
    public function testNoneDeclaresNoDefault(): void
    {
        $fill = Fill::none();

        self::assertSame([false, null, null, false, null], [$fill->declared, $fill->value, $fill->expression, $fill->now, $fill->text]);
    }

    public function testConstantHoldsTheValueAndItsText(): void
    {
        $fill = Fill::constant(7, '7');

        self::assertSame([true, 7, null, false, '7'], [$fill->declared, $fill->value, $fill->expression, $fill->now, $fill->text]);
    }

    public function testConstantDeclaresANullDefault(): void
    {
        $fill = Fill::constant(null, null);

        self::assertSame([true, null, null], [$fill->declared, $fill->value, $fill->text]);
    }
}
