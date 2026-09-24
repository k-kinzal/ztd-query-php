<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Json;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\Column;
use SqlSemantics\Model\TableFunction\Json\ExistsColumn;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\NestedColumns;
use SqlSemantics\Model\TableFunction\Json\Ordinality;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Column::class)]
#[Medium]
final class ColumnTest extends TestCase
{
    public function testClassifiesEveryColumnDeclarationForm(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY, v INTEGER PATH '$.v', ok BOOLEAN EXISTS PATH '$.a', NESTED PATH '$.c[*]' COLUMNS (child FOR ORDINALITY))) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertSame([Ordinality::class, ValueColumn::class, ExistsColumn::class, NestedColumns::class], array_map(static fn (Column $column): string => $column::class, $statement->from->table->columns));
    }
}
