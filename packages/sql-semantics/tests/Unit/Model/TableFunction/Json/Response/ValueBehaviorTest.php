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
use SqlSemantics\Model\TableFunction\Json\Response\ValueBehavior;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ValueBehavior::class)]
#[Medium]
final class ValueBehaviorTest extends TestCase
{
    public function testRepresentsEveryBehaviorPolicy(): void
    {
        self::assertSame(['', 'ERROR', 'NULL', 'EMPTY ARRAY', 'EMPTY OBJECT'], array_column(ValueBehavior::cases(), 'value'));
    }

    #[TestWith(['ERROR ON EMPTY', ValueBehavior::Error, ValueBehavior::Default])]
    #[TestWith(['NULL ON EMPTY', ValueBehavior::Null, ValueBehavior::Default])]
    #[TestWith(['EMPTY ARRAY ON EMPTY', ValueBehavior::EmptyArray, ValueBehavior::Default])]
    #[TestWith(['EMPTY ON EMPTY', ValueBehavior::EmptyArray, ValueBehavior::Default])]
    #[TestWith(['EMPTY OBJECT ON ERROR', ValueBehavior::Default, ValueBehavior::EmptyObject])]
    #[TestWith(['NULL ON EMPTY ERROR ON ERROR', ValueBehavior::Null, ValueBehavior::Error])]
    public function testBindsEmptyAndErrorResponsesSeparately(string $clause, ValueBehavior $onEmpty, ValueBehavior $onError): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (v INTEGER PATH '$.b' " . $clause . ')) AS j');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertInstanceOf(ValueColumn::class, $statement->from->table->columns[0]);
        self::assertSame($onEmpty, $statement->from->table->columns[0]->onEmpty);
        self::assertSame($onError, $statement->from->table->columns[0]->onError);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
