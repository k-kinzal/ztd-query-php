<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextCollation;

#[CoversClass(TextCollation::class)]
#[Small]
final class TextCollationTest extends TestCase
{
    public function testBinaryCollationHasItsNativeCatalogName(): void
    {
        self::assertSame('utf8mb4_bin', TextCollation::Utf8mb4Bin->value);
    }
}
