<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\JsonStorage;
use MySqlMemory\Command\Replication\Binlog\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonStorage::class)]
#[Small]
final class JsonStorageTest extends TestCase
{
    public function testReadPreservesTheUnsignedIntegerType(): void
    {
        $value = (new JsonStorage())->read(new Reader("\x0a" . str_repeat("\xff", 8)));
        self::assertSame('18446744073709551615', $value->text());
        self::assertSame(\MySqlMemory\Value\Json\JsonKind::Unsigned, $value->type);
    }

    public function testValueReadsSignedInlineIntegersAndStrings(): void
    {
        self::assertSame('-1', (new JsonStorage())->value(new Reader("\xff\xff"), 5)->text());
        self::assertSame('"cat"', (new JsonStorage())->value(new Reader("\x03cat"), 12)->text());
    }

    public function testContainerReadsAnInlineArrayWithoutOffsets(): void
    {
        self::assertSame('[1, true]', (new JsonStorage())->container(new Reader("\x02\0\x0a\0\x05\x01\0\x04\x01\0"), 2, 1)->text());
    }

    public function testAtRejectsOffsetsOutsideTheirDocument(): void
    {
        $this->expectExceptionCode(1149);
        (new JsonStorage())->at('abc', -1);
    }

    public function testLengthReadsContinuationBytes(): void
    {
        self::assertSame(16384, (new JsonStorage())->length(new Reader("\x80\x80\x01")));
    }

    public function testOpaqueRetainsExactDecimalAndBinaryValues(): void
    {
        self::assertSame("12.34\0`d12.34", (new JsonStorage())->opaque(new Reader("\xf6\x05\x05\x02\x80\x0c\x22"))->store());
        self::assertSame('"base64:type253:AP8="', (new JsonStorage())->opaque(new Reader("\xfd\x02\0\xff"))->text());
    }
}
