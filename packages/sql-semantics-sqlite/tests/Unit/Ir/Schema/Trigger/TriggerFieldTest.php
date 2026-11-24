<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Trigger;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\RowImage;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Trigger\TriggerField;

#[CoversClass(TriggerField::class)]
#[Small]
final class TriggerFieldTest extends TestCase
{
    public function testKeepsTheImageAndPhysicalPosition(): void
    {
        $value = new TriggerField(RowImage::Old, 4);

        self::assertSame(RowImage::Old, $value->image);
        self::assertSame(4, $value->column);
    }
}
