<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\Format;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Format::class)]
#[Medium]
final class FormatTest extends TestCase
{
    public function testRepresentsEveryDeclaredEncoding(): void
    {
        self::assertSame(['FORMAT JSON', 'FORMAT JSON ENCODING UTF8', 'FORMAT JSON ENCODING UTF16', 'FORMAT JSON ENCODING UTF32'], array_column(Format::cases(), 'value'));
    }

    #[TestWith([Format::Json])]
    #[TestWith([Format::Utf8])]
    #[TestWith([Format::Utf16])]
    #[TestWith([Format::Utf32])]
    public function testBindsTheDocumentAndColumnFormatsFromTheirSpelling(Format $format): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]' " . $format->value . ", '$[*]' COLUMNS (v TEXT " . $format->value . " PATH '$.b')) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertSame($format, $statement->from->table->document->format);
        self::assertInstanceOf(ValueColumn::class, $statement->from->table->columns[0]);
        self::assertSame($format, $statement->from->table->columns[0]->format);
        self::assertSame('SELECT "j"."v" AS "v" FROM JSON_TABLE(\'[]\' ' . $format->value . ', \'$[*]\' COLUMNS("v" text ' . $format->value . ' PATH \'$.b\')) AS "j"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
