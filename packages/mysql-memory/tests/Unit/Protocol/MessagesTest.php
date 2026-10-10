<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol;

use MySqlMemory\Protocol\Capability;
use MySqlMemory\Protocol\Messages;
use MySqlMemory\Result\ResultColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Messages::class)]
#[Small]
final class MessagesTest extends TestCase
{
    public function testHandshakeWritesProtocolVersion10(): void
    {
        $payload = (new Messages())->handshake('8.4.7', 7, 'abcdefghijklmnopqrst', 2);

        self::assertSame(
            "\x0A8.4.7\x00\x07\x00\x00\x00abcdefgh\x00\x0F\xA2\xFF\x02\x00\x3F\x00\x15" . str_repeat("\x00", 10) . "ijklmnopqrst\x00mysql_native_password\x00",
            $payload,
        );
    }

    public function testHandshakeOffersTheCapabilitiesOfTheServer(): void
    {
        self::assertSame(
            Capability::LONG_PASSWORD | Capability::FOUND_ROWS | Capability::LONG_FLAG | Capability::CONNECT_WITH_DB | Capability::PROTOCOL_41
            | Capability::TRANSACTIONS | Capability::SECURE_CONNECTION | Capability::MULTI_STATEMENTS | Capability::MULTI_RESULTS
            | Capability::PS_MULTI_RESULTS | Capability::PLUGIN_AUTH | Capability::CONNECT_ATTRS | Capability::PLUGIN_AUTH_LENENC_CLIENT_DATA,
            Messages::CAPABILITIES,
        );
        self::assertSame(0, Messages::CAPABILITIES & Capability::SSL);
        self::assertSame(0, Messages::CAPABILITIES & Capability::DEPRECATE_EOF);
    }

    public function testOkWritesTheCountsStatusAndWarnings(): void
    {
        self::assertSame("\x00\x02\x05\x02\x00\x01\x00", (new Messages())->ok(2, 5, 2, 1));
        self::assertSame("\x00\xFC\x2C\x01\x00\x03\x00\x00\x00", (new Messages())->ok(300, 0, 3, 0));
    }

    public function testOkAppendsTheInformationText(): void
    {
        self::assertSame("\x00\x02\x00\x02\x00\x00\x00\x26Records: 2  Duplicates: 0  Warnings: 0", (new Messages())->ok(2, 0, 2, 0, 'Records: 2  Duplicates: 0  Warnings: 0'));
    }

    public function testErrorWritesTheNumberStateAndMessage(): void
    {
        self::assertSame("\xFF\x7A\x04#42S02Table 'd.t' doesn't exist", (new Messages())->error(1146, '42S02', "Table 'd.t' doesn't exist"));
    }

    public function testErrorPadsTheStateToFiveCharacters(): void
    {
        self::assertSame("\xFF\x51\x04#HY000Unknown error", (new Messages())->error(1105, 'HY', 'Unknown error'));
    }

    public function testEofWritesTheWarningsAndStatus(): void
    {
        self::assertSame("\xFE\x01\x00\x0A\x00", (new Messages())->eof(1, 10));
    }

    public function testColumnCountWritesALengthEncodedInteger(): void
    {
        self::assertSame("\x02", (new Messages())->columnCount(2));
        self::assertSame("\xFC\x2C\x01", (new Messages())->columnCount(300));
    }

    public function testColumnWritesAProtocol41Definition(): void
    {
        $column = new ResultColumn('n', Field::Long, 11, 0, 3, 63, 'id', 'a', 't', 'd');

        self::assertSame("\x03def\x01d\x01a\x01t\x01n\x02id\x0C\x3F\x00\x0B\x00\x00\x00\x03\x03\x00\x00\x00\x00", (new Messages())->column($column));
    }

    public function testColumnWritesTheLengthOfAnExpressionOfACharacterSet(): void
    {
        $column = new ResultColumn('Message', Field::VarString, 2048, 31, 1, 33);

        self::assertSame("\x03def\x00\x00\x00\x07Message\x00\x0C\x21\x00\x00\x08\x00\x00\xFD\x01\x00\x1F\x00\x00", (new Messages())->column($column));
    }

    public function testColumnLimitsTheLengthToFourBytes(): void
    {
        $column = new ResultColumn('j', Field::LongBlob, 0x1FFFFFFFF, 0, 16, 63);

        self::assertSame("\x03def\x00\x00\x00\x01j\x00\x0C\x3F\x00\xFF\xFF\xFF\xFF\xFB\x10\x00\x00\x00\x00", (new Messages())->column($column));
    }

    public function testTextRowWritesEachValueAsALengthEncodedString(): void
    {
        self::assertSame("\x011\xFB\x00\x03ink", (new Messages())->textRow(['1', null, '', 'ink']));
        self::assertSame('', (new Messages())->textRow([]));
    }

    public function testHandshakeNamesTheCollationOfTheConnections(): void
    {
        self::assertSame("\x08", (new Messages())->handshake('5.7.44', 7, 'abcdefghijklmnopqrst', 2, 8)[23]);
    }
}
