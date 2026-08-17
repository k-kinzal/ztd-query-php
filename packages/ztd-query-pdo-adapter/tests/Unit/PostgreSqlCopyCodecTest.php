<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Adapter\Pdo\PostgreSqlCopyCodec;
use ZtdQuery\Schema\TableDefinition;

#[CoversClass(PostgreSqlCopyCodec::class)]
final class PostgreSqlCopyCodecTest extends TestCase
{
    public function testRelationParsesAndQuotesOnlyPostgreSqlIdentifiers(): void
    {
        $codec = new PostgreSqlCopyCodec();

        self::assertSame(['name' => 'users', 'sql' => '"public"."users"'], $codec->relation('PUBLIC.Users'));
        self::assertSame(['name' => 'Odd.Table', 'sql' => '"Odd Schema"."Odd.Table"'], $codec->relation('"Odd Schema"."Odd.Table"'));

        $this->expectException(\ValueError::class);
        $codec->relation('users; DELETE FROM users');
    }

    public function testRelationRequotesEmbeddedIdentifierQuotes(): void
    {
        self::assertSame(
            ['name' => 'a"b', 'sql' => '"a""b"'],
            (new PostgreSqlCopyCodec())->relation('"a""b"'),
        );
    }

    #[DataProvider('providerInvalidRelation')]
    public function testRelationRejectsInvalidStructure(string $relation): void
    {
        $this->expectException(\ValueError::class);

        (new PostgreSqlCopyCodec())->relation($relation);
    }

    public function testColumnsExcludeGeneratedColumnsAndParseExplicitFields(): void
    {
        $codec = new PostgreSqlCopyCodec();
        $definition = new TableDefinition(
            ['id', 'display_name', 'computed'],
            ['id' => 'INTEGER', 'display_name' => 'TEXT', 'computed' => 'TEXT'],
            ['id'],
            ['id'],
            [],
            generatedExpressions: ['computed' => 'display_name'],
        );

        self::assertSame(['id', 'display_name'], $codec->columns(null, $definition));
        self::assertSame(['id', 'Display Name'], $codec->columns('ID, "Display Name"', $definition));
        self::assertSame('"id", "Display Name"', $codec->columnListSql(['id', 'Display Name']));
    }

    #[DataProvider('providerInvalidFields')]
    public function testColumnsRejectInvalidFieldLists(string $fields): void
    {
        $this->expectException(\ValueError::class);

        (new PostgreSqlCopyCodec())->columns(
            $fields,
            new TableDefinition(['id'], ['id' => 'INTEGER'], ['id'], ['id'], []),
        );
    }

    public function testColumnListRejectsTablesWithoutWritableColumns(): void
    {
        $this->expectException(\ValueError::class);

        (new PostgreSqlCopyCodec())->columnListSql([]);
    }

    public function testEncodeRowUsesPostgreSqlTextEscapesAndNativeScalarOutput(): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        self::assertSame(2, fwrite($stream, "\0\xff"));
        rewind($stream);

        $row = (new PostgreSqlCopyCodec())->encodeRow(
            ["a\tb\nc\\d", null, true, false, $stream],
            '|',
            '\\N',
        );

        self::assertSame("a\\tb\\nc\\\\d|\\N|t|f|\\\\x00ff\n", $row);
    }

    public function testEncodeRowEscapesEveryPostgreSqlControlAndDelimiter(): void
    {
        self::assertSame(
            "\\b\\f\\n\\r\\t\\v\\\\\\|\n",
            (new PostgreSqlCopyCodec())->encodeRow(["\x08\x0C\n\r\t\x0B\\|"], '|', '\\N'),
        );
        self::assertSame(
            "7|1.5\n",
            (new PostgreSqlCopyCodec())->encodeRow([7, 1.5], '|', '\\N'),
        );
    }

    public function testEncodeRowRejectsUnsupportedValues(): void
    {
        $this->expectException(\ValueError::class);

        (new PostgreSqlCopyCodec())->encodeRow([new \stdClass()], '|', '\\N');
    }

    public function testEncodeRowValidatesSeparator(): void
    {
        $this->expectException(\ValueError::class);

        (new PostgreSqlCopyCodec())->encodeRow(['value'], '', '\\N');
    }

    public function testDecodeRowHonorsNullBeforeEscapesAndSupportsByteEscapes(): void
    {
        $values = (new PostgreSqlCopyCodec())->decodeRow(
            "\\N|\\\\N|a\\|b|line\\nfeed|\\101\\x42\r\n",
            '|',
            '\\N',
        );

        self::assertSame([null, '\\N', 'a|b', "line\nfeed", 'AB'], $values);
    }

    public function testDecodeRowSupportsEveryControlUnknownAndVariableLengthByteEscape(): void
    {
        $codec = new PostgreSqlCopyCodec();

        self::assertSame(
            ["\x08\x0C\n\r\t\x0B\\|q"],
            $codec->decodeRow('\\b\\f\\n\\r\\t\\v\\\\\\|\\q', '|', '\\N'),
        );
        self::assertSame(
            ["\0", "\x07", "\n", 'A4', "\x04", 'O', 'Oa'],
            $codec->decodeRow('\\0|\\7|\\12|\\1014|\\x4|\\x4F|\\x4Fa', '|', '\\N'),
        );
        self::assertSame(['S', 'tail'], $codec->decodeRow('\\1232tail', '2', '\\N'));
    }

    #[DataProvider('providerLineEnding')]
    public function testDecodeRowAcceptsEverySupportedLineEnding(string $lineEnding): void
    {
        self::assertSame(['value'], (new PostgreSqlCopyCodec())->decodeRow('value' . $lineEnding, '|', '\\N'));
    }

    #[DataProvider('providerInvalidRow')]
    public function testDecodeRowRejectsMalformedRecords(string $row): void
    {
        $this->expectException(\ValueError::class);

        (new PostgreSqlCopyCodec())->decodeRow($row, '|', '\\N');
    }

    public function testDecodeRowPreservesEmptyFields(): void
    {
        self::assertSame(['', ''], (new PostgreSqlCopyCodec())->decodeRow('|', '|', '\\N'));
    }

    public function testSeparatorMustBeExactlyOneByte(): void
    {
        $this->expectException(\ValueError::class);

        (new PostgreSqlCopyCodec())->decodeRow('value', '||', '\\N');
    }

    /** @return iterable<string, array{string}> */
    public static function providerInvalidRelation(): iterable
    {
        yield 'empty' => [''];
        yield 'trailing qualifier' => ['users.'];
        yield 'operator' => ['users+other'];
        yield 'too many qualifiers' => ['catalog.public.users'];
        yield 'non-identifier' => ['*'];
    }

    /** @return iterable<string, array{string}> */
    public static function providerInvalidFields(): iterable
    {
        yield 'empty' => [''];
        yield 'trailing comma' => ['id,'];
        yield 'expression' => ['id + value'];
        yield 'duplicate' => ['id, id'];
        yield 'non-identifier' => ['*'];
    }

    /** @return iterable<string, array{string}> */
    public static function providerLineEnding(): iterable
    {
        yield 'none' => [''];
        yield 'line feed' => ["\n"];
        yield 'carriage return' => ["\r"];
        yield 'carriage return and line feed' => ["\r\n"];
    }

    /** @return iterable<string, array{string}> */
    public static function providerInvalidRow(): iterable
    {
        yield 'unescaped line feed' => ["first\nsecond"];
        yield 'unescaped carriage return' => ["first\rsecond"];
        yield 'incomplete escape' => ['value\\'];
        yield 'end marker' => ['\\.'];
        yield 'octal overflow' => ['\\400'];
    }
}
