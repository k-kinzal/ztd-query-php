<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Kind;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\BinaryMark;

#[CoversClass(BinaryMark::class)]
#[Small]
final class BinaryMarkTest extends TestCase
{
    public function testCasesNameEveryPlacementOfTheBinaryAttribute(): void
    {
        self::assertSame(['Absent', 'Leading', 'Trailing'], array_column(BinaryMark::cases(), 'name'));
        self::assertSame(['absent', 'leading', 'trailing'], array_column(BinaryMark::cases(), 'value'));
    }
}
