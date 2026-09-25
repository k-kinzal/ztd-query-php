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
use SqlSemantics\Model\TableFunction\Xml\ValueColumn;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(ValueColumn::class)]
#[Medium]
final class ValueColumnTest extends TestCase
{
    public function testRetainsPathDefaultAndNullabilityDeclarations(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT x.* FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS a INTEGER PATH '@a' DEFAULT 1 NOT NULL, b TEXT) AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(XmlTable::class, $statement->from->table);
        $first = $statement->from->table->columns[0];
        self::assertInstanceOf(ValueColumn::class, $first);
        self::assertSame('a', $first->name);
        self::assertSame('integer', $first->type->name);
        self::assertSame("'@a'", $first->path?->spelling());
        self::assertSame('1', $first->default?->spelling());
        self::assertTrue($first->notNull);
        $second = $statement->from->table->columns[1];
        self::assertInstanceOf(ValueColumn::class, $second);
        self::assertNull($second->path);
        self::assertNull($second->default);
        self::assertFalse($second->notNull);
        self::assertSame([Nullability::NotNull, Nullability::MaybeNull], array_map(static fn ($output): Nullability => $output->expression->nullability, $statement->from->outputs));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testIsNullableWithAnImplicitPathByDefault(): void
    {
        $column = new ValueColumn('v', TypeDescriptor::builtin(Dialect::PostgreSql, 'text'));
        self::assertNull($column->path);
        self::assertNull($column->default);
        self::assertFalse($column->notNull);
    }
}
