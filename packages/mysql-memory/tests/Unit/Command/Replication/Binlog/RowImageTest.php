<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Reader;
use MySqlMemory\Command\Replication\Binlog\RowImage;
use MySqlMemory\Command\Replication\Binlog\TableMap;
use MySqlMemory\Instance;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(RowImage::class)]
#[Small]
final class RowImageTest extends TestCase
{
    public function testRowConsumesNullBitsOnlyForPresentColumns(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t (a INT,b VARCHAR(20),c INT)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $map = new TableMap(1, 'd', 't', [3, 15, 3], ['', "\x50\0", '']);
        self::assertSame([0 => 7, 2 => null], (new RowImage())->row(new Reader("\x02\x07\0\0\0"), $map, "\x05", $table->definition->columns));
    }

    public function testValueReadsTheSourceStringWidth(): void
    {
        self::assertSame('abc', (new RowImage())->value(new Reader("\x03\0abc"), 15, "\0\x02", Domain::string(512, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())));
    }

    public function testIntegerPreservesSignedAndUnsignedWidths(): void
    {
        self::assertSame(-1, (new RowImage())->integer(new Reader("\xff\xff\xff"), 3, false));
        self::assertSame(16777215, (new RowImage())->integer(new Reader("\xff\xff\xff"), 3, true));
    }

    public function testFloatingReadsLittleEndianRowImage(): void
    {
        self::assertSame(1.5, (new RowImage())->floating(new Reader(pack('g', 1.5)), 4));
        self::assertSame(-2.5, (new RowImage())->floating(new Reader(pack('e', -2.5)), 8));
    }

    public function testYearRetainsZeroAndTheStorageOffset(): void
    {
        self::assertSame(0, (new RowImage())->year(new Reader("\0")));
        self::assertSame(2024, (new RowImage())->year(new Reader(chr(124))));
    }

    public function testStringResolvesMemberIndexesAndCharacterWidths(): void
    {
        $enum = new Domain(Kind::String, Field::Enum, members: ['first', 'second']);
        self::assertSame('second', (new RowImage())->string(new Reader("\x02"), "\xf7\x01", $enum));
        self::assertSame('abc' . str_repeat("\0", 17), (new RowImage())->string(new Reader("\x03abc"), "\xfe\x14", Domain::string(20, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())));
    }
}
