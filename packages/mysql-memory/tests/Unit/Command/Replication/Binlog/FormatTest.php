<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Replication\Binlog;

use MySqlMemory\Command\Replication\Binlog\Format;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Format::class)]
#[Small]
final class FormatTest extends TestCase
{
    public function testReadRetainsTheAdvertisedEventLayout(): void
    {
        $bytes = base64_decode('PZ/Yag8BAAAAewAAAH8AAAABAAQAOS4xLjAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEwANAAgAAAAABAAEAAAAYwAEGggAAAAAAAACAAAACgoKKioAEjQACigAAAERK3oN', true);
        self::assertIsString($bytes);
        $format = Format::read($bytes);
        self::assertSame([19, 4], [$format->headerLength, $format->checksumLength]);
    }

    public function testPostHeaderReadsTableAndRowHeaderSizes(): void
    {
        $bytes = base64_decode('PZ/Yag8BAAAAewAAAH8AAAABAAQAOS4xLjAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEwANAAgAAAAABAAEAAAAYwAEGggAAAAAAAACAAAACgoKKioAEjQACigAAAERK3oN', true);
        self::assertIsString($bytes);
        $format = Format::read($bytes);
        self::assertSame([8, 10, 0], [$format->postHeader(19), $format->postHeader(30), $format->postHeader(255)]);
    }

    public function testBodyExcludesTheCommonHeaderAndChecksum(): void
    {
        $format = new Format(19, '', 4);
        self::assertSame('abc', $format->body(str_repeat("\0", 19) . 'abc' . str_repeat("\0", 4))->bytes(3));
    }
}
