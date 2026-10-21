<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\DecimalStorage;
use MySqlMemory\Command\Replication\Binlog\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecimalStorage::class)]
#[Small]
final class DecimalStorageTest extends TestCase
{
    public function testReadPreservesSignScaleAndLeadingZeroGroups(): void
    {
        self::assertSame('12.34', (new DecimalStorage())->read(new Reader("\x80\x0c\x22"), "\x05\x02"));
        self::assertSame('-12.34', (new DecimalStorage())->read(new Reader("\x7f\xf3\xdd"), "\x05\x02"));
        self::assertSame('0.00', (new DecimalStorage())->read(new Reader("\x80"), "\x02\x02"));
        self::assertSame('1234567890123456789.123456789', (new DecimalStorage())->read(new Reader("\x81" . pack('N3', 234567890, 123456789, 123456789)), "\x1c\x09"));
    }

    public function testGroupsRejectsOutOfRangePackedDigits(): void
    {
        $this->expectExceptionCode(1149);
        (new DecimalStorage())->groups(new Reader("\xff"), 2);
    }
}
