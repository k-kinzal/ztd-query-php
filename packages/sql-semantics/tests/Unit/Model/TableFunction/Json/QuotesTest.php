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
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Quotes;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Quotes::class)]
#[Medium]
final class QuotesTest extends TestCase
{
    public function testRepresentsEveryQuotesPolicy(): void
    {
        self::assertSame(['', 'KEEP QUOTES', 'OMIT QUOTES'], array_column(Quotes::cases(), 'value'));
    }

    #[TestWith(['KEEP QUOTES', Quotes::Keep, ' KEEP QUOTES'])]
    #[TestWith(['KEEP QUOTES ON SCALAR STRING', Quotes::Keep, ' KEEP QUOTES'])]
    #[TestWith(['OMIT QUOTES', Quotes::Omit, ' OMIT QUOTES'])]
    #[TestWith(['', Quotes::Default, ''])]
    public function testBindsThePolicyAndWritesItsCanonicalSpelling(string $clause, Quotes $quotes, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v TEXT PATH '$.b' " . $clause . ')) AS j');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertInstanceOf(ValueColumn::class, $statement->from->table->columns[0]);
        self::assertSame($quotes, $statement->from->table->columns[0]->quotes);
        self::assertSame('SELECT "j"."v" AS "v" FROM JSON_TABLE(\'[]\', \'$[*]\' COLUMNS("v" text PATH \'$.b\'' . $expected . ')) AS "j"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
