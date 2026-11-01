<?php

declare(strict_types=1);

namespace Tests\Unit\Storage;

use MySqlMemory\Evaluation\ExactDecimal;
use MySqlMemory\Instance;
use MySqlMemory\Storage\ChecksumField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChecksumField::class)]
#[Small]
final class ChecksumFieldTest extends TestCase
{
    /**
     * @param int|float|string|null $value The stored value
     */
    #[DataProvider('providerFields')]
    public function testValueEncodesTheStoredBytesOfTheField(string $type, int|float|string|null $value, string $bytes): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (c ' . $type . ')');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame(bin2hex($bytes), bin2hex((new ChecksumField())->value($table->definition->columns[0]->domain, $value)));
    }

    /**
     * @return iterable<string, array{string, int|float|string|null, string}>
     */
    public static function providerFields(): iterable
    {
        yield 'tiny' => ['TINYINT', 5, "\x05"];
        yield 'short' => ['SMALLINT', 258, "\x02\x01"];
        yield 'medium' => ['MEDIUMINT', 65793, "\x01\x01\x01"];
        yield 'long' => ['INT', 1, "\x01\x00\x00\x00"];
        yield 'negative long' => ['INT', -1, "\xff\xff\xff\xff"];
        yield 'longlong' => ['BIGINT', 1, "\x01\x00\x00\x00\x00\x00\x00\x00"];
        yield 'float' => ['FLOAT', 1.0, pack('g', 1.0)];
        yield 'double' => ['DOUBLE', 1.5, pack('e', 1.5)];
        yield 'year' => ['YEAR', 2024, chr(124)];
        yield 'zero year' => ['YEAR', 0, "\x00"];
        yield 'null integer resets to zero' => ['INT', null, "\x00\x00\x00\x00"];
        yield 'date is little endian' => ['DATE', '2024-01-02', "\x22\xd0\x0f"];
        yield 'fixed string is space padded to its byte width' => ['CHAR(3)', 'ab', 'ab' . str_repeat(' ', 10)];
        yield 'latin1 fixed string is space padded per character' => ['CHAR(3) CHARACTER SET latin1', 'ab', 'ab '];
        yield 'binary is zero padded' => ['BINARY(3)', 'ab', "ab\x00"];
        yield 'varchar has no length prefix' => ['VARCHAR(10)', 'abc', 'abc'];
        yield 'enum stores its ordinal' => ["ENUM('x','y')", 'y', "\x02"];
        yield 'set stores its bitmap' => ["SET('p','q')", 'p,q', "\x03"];
        yield 'bit is big endian' => ['BIT(9)', chr(1) . chr(2), "\x01\x02"];
    }

    public function testDecimalEncodesSignedNineDigitGroups(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (c DECIMAL(10,2))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $domain = $table->definition->columns[0]->domain;

        self::assertSame('8000000132', bin2hex((new ChecksumField())->value($domain, new ExactDecimal('1.50', 2))));
        self::assertSame('7ffffffee6', bin2hex((new ChecksumField())->value($domain, new ExactDecimal('-1.25', 2))));
    }

    public function testGroupPacksNineDigitsIntoFourBytesAndFewerIntoLess(): void
    {
        self::assertSame('3b9ac9ff', bin2hex((new ChecksumField())->group('999999999')));
        self::assertSame('01', bin2hex((new ChecksumField())->group('1')));
        self::assertSame('32', bin2hex((new ChecksumField())->group('50')));
        self::assertSame('0003e8', bin2hex((new ChecksumField())->group('001000')));
    }

    public function testMembersIgnoresUnknownSetMembers(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query("CREATE TABLE t (s SET('p','q'), e ENUM('x','y'))");
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);

        self::assertSame("\x00", (new ChecksumField())->members($table->definition->columns[0]->domain, ''));
        self::assertSame("\x00", (new ChecksumField())->members($table->definition->columns[1]->domain, 'z'));
    }

    public function testFixedPadsToTheByteWidthOfTheCharset(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (a CHAR(2) CHARACTER SET latin1, b CHAR(2), c BINARY(2))');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $field = new ChecksumField();

        self::assertSame('x ', $field->fixed($table->definition->columns[0]->domain, 'x'));
        self::assertSame('x' . str_repeat(' ', 7), $field->fixed($table->definition->columns[1]->domain, 'x'));
        self::assertSame("x\x00", $field->fixed($table->definition->columns[2]->domain, 'x'));
    }

    public function testTemporalEncodesDatesLittleEndianAndResetsNull(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE t (d DATE, t TIME)');
        $table = $session->instance->dictionary->table('d', 't');
        self::assertNotNull($table);
        $field = new ChecksumField();

        self::assertSame('22d00f', bin2hex($field->temporal($table->definition->columns[0]->domain, '2024-01-02')));
        self::assertSame('000000', bin2hex($field->temporal($table->definition->columns[0]->domain, null)));
        self::assertSame(bin2hex($field->temporal($table->definition->columns[1]->domain, '00:00:00')), bin2hex($field->temporal($table->definition->columns[1]->domain, null)));
    }
}
