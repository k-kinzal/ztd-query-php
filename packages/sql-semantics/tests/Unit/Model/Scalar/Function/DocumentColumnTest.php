<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Scalar\Function;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Function\DocumentColumn;
use SqlSemantics\Model\TableFunction\Json\Ordinality;
use SqlSemantics\Model\TableFunction\Json\ValueColumn;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Type\Nullability;

#[CoversClass(DocumentColumn::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DocumentColumnTest extends TestCase
{
    public function testInputsCollectTheDocumentRowPathAndColumnPaths(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.n FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY, label VARCHAR(50) PATH '$.name')) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $column = $relation->outputs[0]->expression;
        self::assertInstanceOf(DocumentColumn::class, $column);
        self::assertSame(["'[]'", "'$[*]'", "'$.name'"], array_map(static fn ($input): ?string => $input->spelling(), $column->inputs()));
        self::assertSame(ExpressionKind::DocumentColumn, $column->kind);
    }

    public function testSpellingIsTheDeclaredColumnName(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT x.n FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY, value INTEGER PATH '@id') AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        self::assertSame(['n', 'value'], array_map(static fn ($output): ?string => $output->expression->spelling(), $relation->outputs));
    }

    public function testOrdinalityIsANotNullIntegerAndValuesMayBeNull(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.n FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY, label VARCHAR(50) PATH '$.name')) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $ordinality = $relation->outputs[0]->expression;
        self::assertInstanceOf(DocumentColumn::class, $ordinality);
        self::assertInstanceOf(Ordinality::class, $ordinality->column);
        self::assertSame('integer', $ordinality->type->name);
        self::assertSame(Nullability::NotNull, $ordinality->nullability);
        $label = $relation->outputs[1]->expression;
        self::assertInstanceOf(DocumentColumn::class, $label);
        self::assertInstanceOf(ValueColumn::class, $label->column);
        self::assertSame('varchar', $label->type->name);
        self::assertSame(Nullability::MaybeNull, $label->nullability);
    }

    public function testWithFactsKeepsTheTableAndColumn(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.n FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $column = $relation->outputs[0]->expression;
        self::assertInstanceOf(DocumentColumn::class, $column);
        $changed = $column->withFacts(new ExpressionFacts($column->type, Nullability::MaybeNull, ['j0']));
        self::assertNotSame($column, $changed);
        self::assertSame(['j0'], $changed->nullExtendedBy);
        self::assertSame([], $column->nullExtendedBy);
        self::assertSame($column->table, $changed->table);
        self::assertSame($column->column, $changed->column);
    }

    public function testRejectsFactsThatChangeTheDeclaredType(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.n FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY, label VARCHAR(50) PATH '$.name')) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(DocumentRelation::class, $relation);
        $ordinality = $relation->outputs[0]->expression;
        self::assertInstanceOf(DocumentColumn::class, $ordinality);
        $label = $relation->outputs[1]->expression;
        $this->expectException(InvalidStructure::class);
        new DocumentColumn($label->facts, $ordinality->source, $ordinality->table, $ordinality->column);
    }

    public function testRejectsAColumnDeclaredByAnotherTable(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $json = $binder->bind("SELECT j.n FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY)) AS j");
        self::assertInstanceOf(BoundSelect::class, $json);
        $xml = $binder->bind("SELECT x.n FROM XMLTABLE ('/rows/row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY) AS x");
        self::assertInstanceOf(BoundSelect::class, $xml);
        self::assertInstanceOf(DocumentRelation::class, $json->from);
        self::assertInstanceOf(DocumentRelation::class, $xml->from);
        $column = $json->from->outputs[0]->expression;
        self::assertInstanceOf(DocumentColumn::class, $column);
        $foreign = $xml->from->outputs[0]->expression;
        self::assertInstanceOf(DocumentColumn::class, $foreign);
        $this->expectException(InvalidStructure::class);
        new DocumentColumn($column->facts, $column->source, $column->table, $foreign->column);
    }
}
