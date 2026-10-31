<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Digest;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Digest\Identifiers;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Diagnostics;
use MySqlMemory\Session\SqlModes;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

#[CoversClass(Identifiers::class)]
#[Small]
final class IdentifiersTest extends TestCase
{
    public function testRoutinesNamesTheIdentifierFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Identifiers())->routines());

        self::assertSame(['UUID', 'UUID_SHORT', 'UUID_TO_BIN', 'BIN_TO_UUID', 'IS_UUID'], $names);
    }

    public function testGeneratorKeepsOneStateForEachServer(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), $session->variables, 0.0));

        self::assertSame((new Identifiers())->generator($frame)[0], (new Identifiers())->generator($frame)[0]);
    }

    public function testUuidAnswersAVersion1UuidThatNeverRepeats(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT UUID(), UUID(), COLLATION(UUID())')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertMatchesRegularExpression('/\A[0-9a-f]{8}-[0-9a-f]{4}-1[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', (string) $result->rows[0][0]);
        self::assertNotSame($result->rows[0][0], $result->rows[0][1]);
        self::assertSame(['utf8mb3_general_ci', 144], [$result->rows[0][2], $result->columns[0]->length]);
    }

    public function testUuidShortCountsUpFromTheServerId(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query('SELECT UUID_SHORT(), UUID_SHORT(), UUID_SHORT() >> 56')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([1, '1'], [(int) $result->rows[0][1] - (int) $result->rows[0][0], $result->rows[0][2]]);
        self::assertSame([21, 33], [$result->columns[0]->length, $result->columns[0]->flags & 33]);
    }

    public function testBytesReadsEachFormOfAUuid(): void
    {
        $identifiers = new Identifiers();

        self::assertSame([16, 16, 16, null, null, null], [strlen((string) $identifiers->bytes('6ccd780c-baba-1026-9564-5b8c656024db')), strlen((string) $identifiers->bytes('6CCD780CBABA102695645B8C656024DB')), strlen((string) $identifiers->bytes('{6ccd780c-baba-1026-9564-5b8c656024db}')), $identifiers->bytes('{6ccd780cbaba102695645b8c656024db}'), $identifiers->bytes('6ccd780cbaba-1026-9564-5b8c656024db'), $identifiers->bytes(' 6ccd780c-baba-1026-9564-5b8c656024db')]);
    }

    public function testSwappedReadsTheFlagAsANumber(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(UUID_TO_BIN('6ccd780c-baba-1026-9564-5b8c656024db', 0.4)), HEX(UUID_TO_BIN('6ccd780c-baba-1026-9564-5b8c656024db', 'x'))")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['1026BABA6CCD780C95645B8C656024DB', '6CCD780CBABA102695645B8C656024DB']], $result->rows);
        self::assertSame([['Warning', '1292', "Truncated incorrect DOUBLE value: 'x'"]], $warnings->rows);
    }

    public function testQuotedCutsTheValueTo128Bytes(): void
    {
        $identifiers = new Identifiers();

        self::assertSame([str_repeat('€', 42) . '??', '\x00\xFF', 'Ã©'], [$identifiers->quoted(str_repeat('€', 100), Domain::string(100, Collation::known('utf8mb4_0900_ai_ci'))), $identifiers->quoted("\x00\xFF", Domain::string(2, Collation::binary())), $identifiers->quoted("\xC3\xA9", Domain::string(2, Collation::known('latin1_swedish_ci')))]);
    }

    public function testUuidToBinMovesTheTimeHighPartFirstWhenSwapped(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(UUID_TO_BIN('6ccd780c-baba-1026-9564-5b8c656024db')), HEX(UUID_TO_BIN('{6ccd780c-baba-1026-9564-5b8c656024db}', 1)), UUID_TO_BIN(NULL), IS_UUID('6ccd780cbaba102695645b8c656024db'), IS_UUID('x'), IS_UUID(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['6CCD780CBABA102695645B8C656024DB', '1026BABA6CCD780C95645B8C656024DB', null, '1', '0', null]], $result->rows);
    }

    public function testUuidToBinRefusesAValueThatIsNotAUuid(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect string value: 'abc' for function uuid_to_bin");

        $session->query("SELECT UUID_TO_BIN('abc')");
    }

    public function testBinToUuidWritesSixteenBytes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT BIN_TO_UUID(X'6ccd780cbaba102695645b8c656024db'), BIN_TO_UUID(X'1026baba6ccd780c95645b8c656024db', 1), BIN_TO_UUID('abcdefghijklmnop')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['6ccd780c-baba-1026-9564-5b8c656024db', '6ccd780c-baba-1026-9564-5b8c656024db', '61626364-6566-6768-696a-6b6c6d6e6f70']], $result->rows);
    }

    public function testBinToUuidRefusesAValueOfAnotherLength(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect string value: '\\x00' for function bin_to_uuid");

        $session->query("SELECT BIN_TO_UUID(X'00')");
    }

    public function testStoreKeepsTheStateForTheServer(): void
    {
        $session = (new Instance())->connect();
        $frame = new Frame(new Context(new SqlModes([]), new Diagnostics(), $session->variables, 0.0));
        (new Identifiers())->store($frame, ['abcdef', 1, 2, 3]);

        self::assertSame(['abcdef', 1, 2, 3], (new Identifiers())->generator($frame));
    }
}
