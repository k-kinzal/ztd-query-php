<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;

#[CoversClass(RowImage::class)]
#[Small]
final class RowImageTest extends TestCase
{
    public function testCasesIdentifyTheNativeRoles(): void
    {
        self::assertSame(['NEW', 'OLD'], array_column(RowImage::cases(), 'value'));
    }
}
