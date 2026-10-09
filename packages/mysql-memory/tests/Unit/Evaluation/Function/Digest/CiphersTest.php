<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Digest;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Digest\Ciphers;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ciphers::class)]
#[Small]
final class CiphersTest extends TestCase
{
    public function testRoutinesNamesTheEncryptionFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Ciphers())->routines());

        self::assertSame(['AES_ENCRYPT', 'AES_DECRYPT', 'DES_ENCRYPT', 'DES_DECRYPT'], $names);
    }

    public function testModeReadsANameOrTheNumberOfOne(): void
    {
        self::assertSame(['aes-256-ofb', 'aes-192-ecb', 'aes-256-cbc', null, null, null], [Ciphers::mode('Aes-256-OFB'), Ciphers::mode('1'), Ciphers::mode('5'), Ciphers::mode('18'), Ciphers::mode('aes-128-cfb'), Ciphers::mode(' aes-128-cbc')]);
    }

    public function testAesEncryptsUnderTheFoldedKeyInEcb(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(AES_ENCRYPT('text', 'key')), AES_DECRYPT(AES_ENCRYPT('text', 'key'), 'key'), AES_DECRYPT('garbage', 'key'), HEX(AES_ENCRYPT('text', 'key', '1234567890123456'))")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['15E36637363712FC2E699B9C95B75393', 'text', null, '15E36637363712FC2E699B9C95B75393']], $result->rows);
        self::assertSame([['Warning', '1618', '<IV> option ignored']], $warnings->rows);
    }

    public function testAesUsesTheModeOfTheSession(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET block_encryption_mode = 'aes-256-cbc'");
        $cbc = $session->query("SELECT HEX(AES_ENCRYPT('text', 'key', '1234567890123456'))")[0];
        $session->query("SET block_encryption_mode = 'aes-192-cfb8'");
        $stream = $session->query("SELECT HEX(AES_ENCRYPT('text', 'key', '1234567890123456')), AES_ENCRYPT('text', 'key', '1234567890123456')")[0];

        self::assertInstanceOf(ResultSet::class, $cbc);
        self::assertInstanceOf(ResultSet::class, $stream);
        self::assertSame([[['53C2A3389ACA49DC6D54A64FF0D653E4']], '0D0B3D84', 16], [$cbc->rows, $stream->rows[0][0], $stream->columns[1]->length]);
    }

    public function testAesRefusesAShortInitializationVector(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET block_encryption_mode = 'aes-128-cbc'");

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('The initialization vector supplied to aes_encrypt is too short. Must be at least 16 bytes long');

        $session->query("SELECT AES_ENCRYPT('text', 'key', '123')");
    }

    public function testAesRefusesACallWithoutAnInitializationVectorInCbc(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET block_encryption_mode = 'aes-128-cbc'");

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Incorrect parameter count in the call to native function 'aes_decrypt'");

        $session->query("SELECT AES_DECRYPT('a', 'k')");
    }

    public function testFoldedXorsTheKeyIntoItsLength(): void
    {
        self::assertSame("\x02b", (new Ciphers())->folded('abc', 2));
    }

    public function testFoldedAnswersZeroBytesForAnEmptyKey(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT HEX(AES_ENCRYPT('a', ''))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([str_repeat("\0", 16), [['8E4A3D4BEB92D54C7E95F67D41DAED59']]], [(new Ciphers())->folded('', 16), $result->rows]);
    }

    public function testDerivedUsesHkdfOrPbkdf2WithSha512(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET block_encryption_mode = 'aes-256-cbc'");
        $result = $session->query("SELECT HEX(AES_ENCRYPT('text', 'key', '1234567890123456', 'hkdf')), HEX(AES_ENCRYPT('text', 'key', '1234567890123456', 'hkdf', 'salt', 'info')), HEX(AES_ENCRYPT('text', 'key', '1234567890123456', 'pbkdf2_hmac', 'salt', ' 2000'))")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['A4162DA9554160ED1D3256CD929D62D6', '8EC3809A498FD3573159A00EC7861637', 'D04495C823A8C080C54A837BBBE88D35']], $result->rows);
    }

    public function testDerivedRefusesAnUnknownFunction(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('KDF method name is not valid. Please use hkdf or pbkdf2_hmac method name');

        $session->query("SELECT AES_ENCRYPT('text', 'key', NULL, 'HKDF')");
    }

    public function testDerivedRefusesTooFewIterations(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('For KDF method pbkdf2_hmac iterations value less than 1000');

        $session->query("SELECT AES_ENCRYPT('text', 'key', NULL, 'pbkdf2_hmac', 'salt', 999)");
    }

    public function testOptionRefusesAnIterationCountOfSixCharacters(): void
    {
        $session = (new Instance())->connect();

        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('KDF option size is invalid, please provide valid size < 6 bytes and not NULL');

        $session->query("SELECT AES_ENCRYPT('text', 'key', NULL, 'pbkdf2_hmac', 'salt', 1000.9)");
    }

    public function testDesKeyIsTheKeyOfEvpBytesToKey(): void
    {
        self::assertSame(24, strlen((new Ciphers())->desKey('key')));
    }

    public function testDesEncryptMarksTheKey(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT HEX(DES_ENCRYPT('abc', 'key')), HEX(DES_ENCRYPT('abc')), HEX(DES_ENCRYPT('abc', 3)), DES_ENCRYPT('', 'k'), DES_ENCRYPT('abc', 10)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([['FF3C503FDC99D78C21', '80A5E0513191EBAE87', '83A5E0513191EBAE87', '', null]], $result->rows);
        self::assertSame(['1287', '1108'], [$warnings->rows[0][1], $warnings->rows[5][1]]);
    }

    public function testDesDecryptAnswersAnUnmarkedTextUnchanged(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $result = $session->query("SELECT DES_DECRYPT(DES_ENCRYPT('abc', 'key'), 'key'), DES_DECRYPT(DES_ENCRYPT('abc', 1)), DES_DECRYPT('abc'), DES_DECRYPT(DES_ENCRYPT('abc', 'key'), 'kez')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['abc', 'abc', 'abc', null]], $result->rows);
    }
}
