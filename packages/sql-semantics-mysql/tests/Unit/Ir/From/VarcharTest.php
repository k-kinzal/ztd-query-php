<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;
use SqlSemantics\Platform\MySql\Ir\From\Varchar;

#[CoversClass(Varchar::class)]
#[Small]
final class VarcharTest extends TestCase
{
    public function testRetainsZeroLengthAndCollation(): void
    {
        $native = new Varchar(0);

        self::assertSame(0, $native->length);
        self::assertSame(TextCollation::Utf8mb4Bin, $native->collation);
    }

    public function testRejectsAnOutOfRangeLength(): void
    {
        $this->expectExceptionMessage('A utf8mb4 VARCHAR length must be between 0 and 16383 characters.');

        new Varchar(16384);
    }
}
