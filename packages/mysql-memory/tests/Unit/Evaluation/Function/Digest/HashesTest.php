<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Digest;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Digest\Hashes;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Hashes::class)]
#[Small]
final class HashesTest extends TestCase
{
    public function testRoutinesNamesTheHashFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Hashes())->routines());

        self::assertSame(['MD5', 'SHA', 'SHA1', 'SHA2', 'RANDOM_BYTES', 'VALIDATE_PASSWORD_STRENGTH', 'PASSWORD', 'OLD_PASSWORD', 'ENCRYPT'], $names);
    }

    public function testWrittenConvertsTheDigitsIntoTheCharacterSetOfTheResult(): void
    {
        self::assertSame(["\x00a\x00b", 'ab'], [(new Hashes())->written('ab', Domain::string(2, Collation::known('utf16_general_ci'))), (new Hashes())->written('ab', Domain::string(2, Collation::known('latin1_swedish_ci')))]);
    }

    public function testDigestHashesTheBytesOfTheArgument(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT MD5('a'), SHA1('a'), SHA('a'), MD5(_latin1'é'), MD5(1.5), MD5(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0cc175b9c0f1b6a831c399e269772661', '86f7e437faa5a7fce15d1ddcb9eaeaea377667b8', '86f7e437faa5a7fce15d1ddcb9eaeaea377667b8', '66ddcd97cfdeabb2f6fb8a999b4bc76f', '6008647277c4454cecd97d33c069f0ca', null]], $result->rows);
        self::assertSame([Field::VarString, 128, 31, 0, 255], [$result->columns[0]->type, $result->columns[0]->length, $result->columns[0]->decimals, $result->columns[0]->flags & 1, $result->columns[0]->charset]);
    }

    public function testKnownWarnsOnceOfALengthKnownWhenTheStatementIsResolved(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a VARCHAR(10) NOT NULL, b INT NOT NULL)');
        $session->query("INSERT INTO t VALUES ('x', 1), ('y', 2), ('z', 3)");
        $empty = $session->query('SELECT SHA2(a, 1) FROM t WHERE b > 10')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];
        $rows = $session->query('SELECT SHA2(a, 1) FROM t')[0];
        $once = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $empty);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertInstanceOf(ResultSet::class, $rows);
        self::assertInstanceOf(ResultSet::class, $once);
        self::assertSame([[], [['Warning', '1583', "Incorrect parameters in the call to native function 'sha2'"]]], [$empty->rows, $warnings->rows]);
        self::assertSame([[[null], [null], [null]], 1], [$rows->rows, count($once->rows)]);
    }

    public function testSha2AnswersTheDigestOfEachLengthAndNullForAnother(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SHA2('a', 224), SHA2('a', 0), SHA2('a', 224.2), SHA2('a', 256.7), SHA2('a', NULL)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['abd37534c7d9a2efb9465de931cd7055ffdb8879563ae98078d6d6d5', 'ca978112ca1bbdcafac231b39a23dc4da786eff8147c4e72b9807785afee48bb', 'abd37534c7d9a2efb9465de931cd7055ffdb8879563ae98078d6d6d5', null, null]], $result->rows);
        self::assertSame([224, 256, 224, 256, 256], array_map(static fn ($column): int => $column->length, $result->columns));
        self::assertCount(2, $warnings->rows);
    }

    public function testSha2WarnsForEachRowOfALengthReadFromTheRow(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a VARCHAR(10) NOT NULL, n INT)');
        $session->query("INSERT INTO t VALUES ('x', 224), ('y', NULL), ('z', 7)");
        $result = $session->query('SELECT SHA2(a, n) FROM t')[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['54a2f7f92a5f975d8096af77a126edda7da60c5aa872ef1b871701ae'], [null], [null]], $result->rows);
        self::assertSame(512, $result->columns[0]->length);
        self::assertCount(2, $warnings->rows);
    }

    public function testRandomBytesAnswersThatManyBytes(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT LENGTH(RANDOM_BYTES(1024)), LENGTH(RANDOM_BYTES('5')), RANDOM_BYTES(NULL), RANDOM_BYTES(1)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['1024', '5', null], array_slice($result->rows[0], 0, 3));
        self::assertSame([1024, 63], [$result->columns[3]->length, $result->columns[3]->charset]);
    }

    public function testRandomBytesRefusesACountOutOfRange(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("length value is out of range in 'random_bytes'");

        $session->query('SELECT RANDOM_BYTES(1025)');
    }

    public function testRoutinesAnswerZeroForThePasswordStrengthWithoutTheComponent(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT VALIDATE_PASSWORD_STRENGTH('Abcdef12!xyz'), VALIDATE_PASSWORD_STRENGTH(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['0', null]], $result->rows);
    }

    public function testPasswordHashesTwiceAndWarnsIn57(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT PASSWORD('a'), PASSWORD(''), PASSWORD(NULL)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['*667F407DE7C6AD07358FA38DAED7828A72014B4E', '', '']], $result->rows);
        self::assertSame([['Warning', '1681', "'PASSWORD' is deprecated and will be removed in a future release."]], $warnings->rows);
    }

    public function testOldPasswordIsTheHashOf323(): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $result = $session->query("SELECT OLD_PASSWORD('a'), OLD_PASSWORD('a b'), OLD_PASSWORD(''), OLD_PASSWORD(NULL)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['60671c896665c3fa', '077ba8cb491e16c5', '', null]], $result->rows);
    }

    public function testEncryptIsCryptAndNullForASaltItDoesNotTake(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT ENCRYPT('abc', 'ab'), ENCRYPT('abc', '\$1\$abcdefgh\$'), ENCRYPT('abc', 'a'), ENCRYPT('abc', '!!'), LENGTH(ENCRYPT('abc'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abFZSxKKdq5s6', '$1$abcdefgh$Kn5qrjcQzV7oAHBJ23Cu3/', null, null, '13']], $result->rows);
    }
}
