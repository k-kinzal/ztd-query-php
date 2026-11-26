<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Generation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Schema\Generation\Storage
 */
final class StorageTest extends TestCase
{
    public function testUsesTheNativeStorageKeywords(): void
    {
        self::assertSame(['VIRTUAL', 'STORED'], array_column(Storage::cases(), 'value'));
    }
}
