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

    public function testOriginRetainsIdentitiesWithoutKeyFlags(): void
    {
        $origin = new ColumnOrigin('d', 'a', 't', 'c', 8, true);
        $domain = Domain::string(100, Collation::known('utf8mb3_bin'), Field::Blob);
        $buffered = ProgramMetadata::origin($origin, $domain);

        self::assertNotNull($buffered);
        self::assertSame(['d', 'a', 't', 'c', 16, true], [$buffered->schema, $buffered->table, $buffered->originalTable, $buffered->column, $buffered->flags, $buffered->exact]);
        self::assertNull(ProgramMetadata::origin(null, $domain));
    }
}
