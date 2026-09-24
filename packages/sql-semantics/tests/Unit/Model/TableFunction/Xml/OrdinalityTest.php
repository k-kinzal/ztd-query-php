<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Xml\Ordinality;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(Ordinality::class)]
#[Medium]
final class OrdinalityTest extends TestCase
{
    public function testProducesANonNullIntegerOutput(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT x.n FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY) AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(XmlTable::class, $statement->from->table);
        $column = $statement->from->table->columns[0];
        self::assertInstanceOf(Ordinality::class, $column);
        self::assertSame('n', $column->name);
        self::assertSame('integer', $statement->outputs[0]->expression->type->name);
        self::assertSame(Nullability::NotNull, $statement->outputs[0]->expression->nullability);
        self::assertSame('SELECT "x"."n" AS "n" FROM XMLTABLE(\'/rows/row\' PASSING \'<rows/>\' COLUMNS "n" FOR ORDINALITY) AS "x"', $statement->toString());
    }
}
