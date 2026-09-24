<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json\Response;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse;
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DefaultResponse::class)]
#[Medium]
final class DefaultResponseTest extends TestCase
{
    public function testKeepsTheDefaultExpressionSeparatelyForEmptyAndErrorCases(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' DEFAULT 0 ON EMPTY DEFAULT -1 ON ERROR)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $column = $statement->from->table->columns[0];
        self::assertInstanceOf(ValueColumn::class, $column);
        self::assertInstanceOf(DefaultResponse::class, $column->onEmpty);
        self::assertSame('0', $column->onEmpty->expression->spelling());
        self::assertInstanceOf(DefaultResponse::class, $column->onError);
        self::assertSame('integer', $column->onError->expression->type->name);
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }

    public function testDoesNotReplaceABehaviorKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' DEFAULT 0 ON EMPTY NULL ON ERROR)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $column = $statement->from->table->columns[0];
        self::assertInstanceOf(ValueColumn::class, $column);
        self::assertInstanceOf(DefaultResponse::class, $column->onEmpty);
        self::assertSame(ValueBehavior::Null, $column->onError);
    }

    public function testRetainsAnUnevaluatedExpression(): void
    {
        $response = new DefaultResponse(Expression::literal('missing', Dialect::MySql));
        self::assertSame("'missing'", $response->expression->spelling());
        self::assertSame(Dialect::MySql, $response->expression->type->dialect);
    }
}
