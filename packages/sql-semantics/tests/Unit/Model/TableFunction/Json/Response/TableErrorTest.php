<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Response\TableError;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableError::class)]
#[Medium]
final class TableErrorTest extends TestCase
{
    public function testRepresentsEveryTableLevelErrorResponse(): void
    {
        self::assertSame(['', 'ERROR', 'EMPTY'], array_column(TableError::cases(), 'value'));
    }

    #[TestWith([' ERROR ON ERROR', TableError::Error])]
    #[TestWith([' EMPTY ON ERROR', TableError::EmptyRows])]
    #[TestWith(['', TableError::Default])]
    public function testBindsTheResponseAndWritesItBack(string $clause, TableError $error): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY)" . $clause . ') AS j');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertSame($error, $statement->from->table->onError);
        self::assertSame('SELECT "j"."n" AS "n" FROM JSON_TABLE(\'[]\', \'$[*]\' COLUMNS("n" FOR ORDINALITY)' . $clause . ') AS "j"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
