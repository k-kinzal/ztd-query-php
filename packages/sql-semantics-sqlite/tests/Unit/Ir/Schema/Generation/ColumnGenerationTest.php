<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Generation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\ColumnGeneration
 */
final class ColumnGenerationTest extends TestCase
{
    public function testRetainsItsModeAndReference(): void
    {
        $slot = new ColumnReference(0);
        $value = new ColumnGeneration(Storage::Stored, $slot);

        self::assertSame(Storage::Stored, $value->mode);
        self::assertSame($slot, $value->expression);
    }
}
