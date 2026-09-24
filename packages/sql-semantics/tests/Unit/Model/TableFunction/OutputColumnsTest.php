<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Json\Column;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Json\Ordinality;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\Model\TableFunction\OutputColumns;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

#[CoversClass(OutputColumns::class)]
#[Medium]
final class OutputColumnsTest extends TestCase
{
    #[TestWith([Dialect::MySql, 'integer unsigned'])]
    #[TestWith([Dialect::PostgreSql, 'integer'])]
    public function testDeriveTypesOrdinalColumnsPerDialectAndMarksNestedOnesNullable(Dialect $dialect, string $type): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY, NESTED PATH '$.c[*]' COLUMNS (child FOR ORDINALITY, value INTEGER PATH '$'))) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        $outputs = $statement->from->outputs;
        self::assertSame(['n', 'child', 'value'], array_column($outputs, 'name'));
        self::assertSame([0, 1, 2], array_column($outputs, 'ordinal'));
        self::assertSame($type, $outputs[0]->expression->type->name);
        self::assertSame(Nullability::NotNull, $outputs[0]->expression->nullability);
        self::assertSame(Nullability::MaybeNull, $outputs[1]->expression->nullability);
        self::assertSame('integer', $outputs[2]->expression->type->name);
    }

    public function testDeriveKeepsXmlOrdinalityAndNotNullDeclarations(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT x.* FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY, a INTEGER PATH '@a' NOT NULL, b TEXT PATH '@b') AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        $outputs = $statement->from->outputs;
        self::assertSame(['n', 'a', 'b'], array_column($outputs, 'name'));
        self::assertSame([Nullability::NotNull, Nullability::NotNull, Nullability::MaybeNull], array_map(static fn ($output): Nullability => $output->expression->nullability, $outputs));
        self::assertSame('text', $outputs[2]->expression->type->name);
    }

    public function testJsonFlattensNestedDeclarationsWhileRememberingNesting(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('{}', '$[*]' COLUMNS (n FOR ORDINALITY, ok INTEGER EXISTS PATH '$.a', NESTED PATH '$.children[*]' COLUMNS (child FOR ORDINALITY, value INTEGER PATH '$'))) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $pairs = OutputColumns::json($statement->from->table->columns);
        self::assertSame([['n', false], ['ok', false], ['child', true], ['value', true]], array_map(static fn (array $pair): array => [$pair[0]->name, $pair[1]], $pairs));
    }

    public function testJsonRejectsAnUnclassifiedColumn(): void
    {
        $this->expectException(InvalidStructure::class);
        OutputColumns::json([new class () implements Column {
        }]);
    }

    public function testTypeReturnsTheDeclaredTypeOfAValueColumn(): void
    {
        $type = TypeDescriptor::builtin(Dialect::PostgreSql, 'text');
        self::assertSame($type, OutputColumns::type(new ValueColumn('v', $type, Expression::literal('$.v', Dialect::PostgreSql)), Dialect::PostgreSql));
        self::assertSame('integer', OutputColumns::type(new Ordinality('n'), Dialect::PostgreSql)->name);
    }
}
