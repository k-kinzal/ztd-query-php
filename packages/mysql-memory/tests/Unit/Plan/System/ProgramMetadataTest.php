<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\System;

use MySqlMemory\Instance;
use MySqlMemory\Plan\ColumnOrigin;
use MySqlMemory\Plan\System\ProgramMetadata;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

#[CoversClass(ProgramMetadata::class)]
#[Small]
final class ProgramMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string, Field, int, int}>
     */
    public static function providerReads(): iterable
    {
        yield 'expression metadata' => ['', Field::LongBlob, 31, 76];
        yield 'sorted metadata' => ['ORDER BY ROUTINE_NAME', Field::Blob, 0, 19];
        yield 'empty sorted result' => ['ORDER BY ROUTINE_NAME LIMIT 0', Field::LongBlob, 31, 76];
        yield 'impossible filter' => ['WHERE FALSE ORDER BY ROUTINE_NAME', Field::LongBlob, 31, 76];
    }

    #[DataProvider('providerReads')]
    public function testBufferedReflectsTemporaryColumnsOnlyWhenRowsCanBeSorted(string $suffix, Field $type, int $decimals, int $length): void
    {
        $result = (new Instance())->connect()->query('SELECT DATA_TYPE, CREATED FROM information_schema.ROUTINES ' . $suffix)[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([$type, $decimals, $length], [$result->columns[0]->type, $result->columns[0]->decimals, $result->columns[1]->length]);
    }

    public function testDomainKeepsNumericTypesAndConvertsTemporaryTextAndTime(): void
    {
        $number = Domain::integer();
        $text = Domain::string(5, Collation::known('utf8mb3_bin'), Field::Enum);
        $time = new Domain(Kind::DateTime, Field::Timestamp, 19, 0, false, Collation::known('utf8mb3_bin'));

        self::assertSame($number, ProgramMetadata::domain($number));
        self::assertSame(Field::VarString, ProgramMetadata::domain($text)->field);
        self::assertSame(0, ProgramMetadata::domain($text)->decimals);
        self::assertSame(Collation::binary(), ProgramMetadata::domain($time)->collation);
    }

    public function testBufferedClearsDecimalsOfSortedStatisticsText(): void
    {
        $session = (new Instance())->connect();
        $direct = $session->query('SELECT INDEX_NAME,IS_VISIBLE FROM information_schema.STATISTICS')[0];
        $sorted = $session->query('SELECT INDEX_NAME,IS_VISIBLE FROM information_schema.STATISTICS ORDER BY INDEX_NAME')[0];
        self::assertInstanceOf(ResultSet::class, $direct);
        self::assertInstanceOf(ResultSet::class, $sorted);

        self::assertSame([31, 31, 0, 0], [$direct->columns[0]->decimals, $direct->columns[1]->decimals, $sorted->columns[0]->decimals, $sorted->columns[1]->decimals]);
    }

    public function testOriginRetainsIdentitiesWithoutKeyFlags(): void
    {
        $origin = new ColumnOrigin('d', 'a', 't', 'c', 8, true);
        $domain = Domain::string(100, Collation::known('utf8mb3_bin'), Field::Blob);
        $buffered = ProgramMetadata::origin($origin, $domain);

        self::assertNotNull($buffered);
        self::assertSame(['d', 'a', 't', 'c', 16, true], [$buffered->schema, $buffered->table, $buffered->originalTable, $buffered->column, $buffered->flags, $buffered->exact]);
        self::assertNull(ProgramMetadata::origin(null, $domain));
    }

    public function testColumnsOriginRetainsObservedBaseNamesAndMaterializedFlags(): void
    {
        $ordinal = ProgramMetadata::columnsOrigin(new ColumnOrigin('information_schema', 'c', 'COLUMNS', 'ORDINAL_POSITION', 53280, true), Domain::integer());
        $name = ProgramMetadata::columnsOrigin(new ColumnOrigin('', 'c', 'COLUMNS', 'COLUMN_NAME', 0, true), Domain::string(64, Collation::known('utf8mb3_bin')));
        $length = ProgramMetadata::columnsOrigin(new ColumnOrigin('', 'c', 'COLUMNS', 'CHARACTER_MAXIMUM_LENGTH', 32896, true), Domain::integer());

        self::assertSame(['information_schema', 'columns', 36896], [$ordinal->schema, $ordinal->originalTable, $ordinal->flags]);
        self::assertSame(['information_schema', '', 'c'], [$name->schema, $name->originalTable, $name->table]);
        self::assertSame(32768, $length->flags);
    }

    public function testDomainMaterializesTheObservedColumnsViewTypes(): void
    {
        $precision = ProgramMetadata::domain(Domain::integer(Field::Long, 10, true), new ColumnOrigin('information_schema', 'c', 'COLUMNS', 'NUMERIC_PRECISION'));
        $comment = ProgramMetadata::domain(Domain::string(2048, Collation::known('utf8mb3_bin')), new ColumnOrigin('', 'c', 'COLUMNS', 'COLUMN_COMMENT'));

        self::assertSame([Field::LongLong, 10, true], [$precision->field, $precision->length, $precision->unsigned]);
        self::assertSame([Field::Blob, 6144, 0], [$comment->field, $comment->length, $comment->decimals]);
    }

    public function testDomainKeepsAnOrdinaryTableNamedColumnsOutsideTheDictionaryRules(): void
    {
        $number = Domain::integer(Field::Long, 10, true);
        $text = Domain::string(2048, Collation::known('utf8mb3_bin'));

        self::assertSame($number, ProgramMetadata::domain($number, new ColumnOrigin('d', 'c', 'COLUMNS', 'NUMERIC_PRECISION')));
        self::assertSame(Field::VarString, ProgramMetadata::domain($text, new ColumnOrigin('d', 'c', 'COLUMNS', 'COLUMN_COMMENT'))->field);
    }

    public function testOriginMaterializesKeyColumnUsageBaseAndComputedFields(): void
    {
        $text = Domain::string(64, Collation::known('utf8mb4_0900_ai_ci'));
        $base = ProgramMetadata::origin(new ColumnOrigin('information_schema', 'k', 'KEY_COLUMN_USAGE', 'TABLE_NAME', 20609), $text);
        $computed = ProgramMetadata::origin(new ColumnOrigin('', 'k', 'KEY_COLUMN_USAGE', 'COLUMN_NAME'), $text);

        self::assertNotNull($base);
        self::assertNotNull($computed);
        self::assertSame(['tables', 4225], [$base->originalTable, $base->flags]);
        self::assertSame(['information_schema', ''], [$computed->schema, $computed->originalTable]);
    }

    public function testBufferedRetainsTableDictionaryOriginsAfterSorting(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d; CREATE TABLE d.t(a INT)');
        $result = $session->query("SELECT TABLE_NAME,ROW_FORMAT,CREATE_OPTIONS FROM information_schema.tables WHERE table_schema='d' ORDER BY table_name")[0];
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame(['tables', 'tables', ''], array_map(static fn ($column): string => $column->originalTable, $result->columns));
        self::assertSame([0, 0, 0], array_map(static fn ($column): int => $column->decimals, $result->columns));
    }

}
