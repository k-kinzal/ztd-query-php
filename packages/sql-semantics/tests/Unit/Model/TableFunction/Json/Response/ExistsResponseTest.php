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
use SqlSemantics\Model\TableFunction\Json\ExistsColumn;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Response\ExistsResponse;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ExistsResponse::class)]
#[Medium]
final class ExistsResponseTest extends TestCase
{
    public function testRepresentsEveryExistenceErrorResponse(): void
    {
        self::assertSame(['', 'ERROR', 'TRUE', 'FALSE', 'UNKNOWN'], array_column(ExistsResponse::cases(), 'value'));
    }

    #[TestWith([ExistsResponse::Error])]
    #[TestWith([ExistsResponse::True])]
    #[TestWith([ExistsResponse::False])]
    #[TestWith([ExistsResponse::Unknown])]
    public function testBindsTheResponseFromItsSpelling(ExistsResponse $response): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (ok BOOLEAN EXISTS PATH '$.a' " . $response->value . ' ON ERROR)) AS j');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertInstanceOf(ExistsColumn::class, $statement->from->table->columns[0]);
        self::assertSame($response, $statement->from->table->columns[0]->onError);
        self::assertSame('SELECT "j"."ok" AS "ok" FROM JSON_TABLE(\'[]\', \'$[*]\' COLUMNS("ok" boolean EXISTS PATH \'$.a\' ' . $response->value . ' ON ERROR)) AS "j"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
