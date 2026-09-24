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
use SqlSemantics\Model\TableFunction\Json\Ordinality;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Ordinality::class)]
#[Medium]
final class OrdinalityTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'integer'])]
    #[TestWith([Dialect::MySql, 'integer unsigned'])]
    public function testProducesANonNullIntegerOutputWithoutAPath(Dialect $dialect, string $type): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind("SELECT j.n FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $column = $statement->from->table->columns[0];
        self::assertInstanceOf(Ordinality::class, $column);
        self::assertSame('n', $column->name);
        self::assertSame($type, $statement->outputs[0]->expression->type->name);
        self::assertSame(Nullability::NotNull, $statement->outputs[0]->expression->nullability);
    }
}
