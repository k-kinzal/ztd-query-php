<?php

declare(strict_types=1);

namespace Tests\Unit\TypeMapper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\TypeMapper\IntegerRange;
use SqlFixture\TypeMapper\IntegerWidth;

#[CoversClass(IntegerWidth::class)]
#[UsesClass(IntegerRange::class)]
final class IntegerWidthTest extends TestCase
{
    public function testWidthIdentifiesAnIntegerStorageDomain(): void
    {
        $range = new IntegerRange(IntegerWidth::Bits24, unsigned: true);
        self::assertSame(16777215, $range->maximum);
    }
}
