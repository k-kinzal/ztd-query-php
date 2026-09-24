<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Column;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Dialect;
use SqlSemantics\Schema\Column\Storage;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Storage::class)]
#[Medium]
final class StorageTest extends TestCase
{
    public function testRepresentsEveryStorageMedium(): void
    {
        self::assertSame(['default', 'disk', 'memory'], array_column(Storage::cases(), 'value'));
    }

    public function testClassifiesTheStorageOption(): void
    {
        $table = (new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT STORAGE MEMORY, b INT STORAGE DISK, c INT)')->tables[0];
        self::assertSame(Storage::Memory, $table->columns[0]->attributes->storage);
        self::assertSame(Storage::Disk, $table->columns[1]->attributes->storage);
        self::assertNull($table->columns[2]->attributes->storage);
    }
}
