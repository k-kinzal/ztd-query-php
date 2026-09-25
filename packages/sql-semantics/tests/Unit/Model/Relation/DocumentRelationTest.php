<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Relation\DocumentRelation::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class DocumentRelationTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql])]
    #[TestWith([Dialect::MySql])]
    public function testWithScopeKeepsNestedPathsAndColumnDefaults(Dialect $dialect): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind("SELECT j.* FROM JSON_TABLE ('{}', '$[*]' COLUMNS (n FOR ORDINALITY, label VARCHAR(50) PATH '$.name' DEFAULT 'missing' ON EMPTY ERROR ON ERROR, NESTED PATH '$.children[*]' COLUMNS (child FOR ORDINALITY, value INTEGER PATH '$'))) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        self::assertCount(3, $relation->table->columns);
        $label = $relation->table->columns[1];
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\ValueColumn::class, $label);
        self::assertSame("'$.name'", $label->path?->spelling());
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\Response\DefaultResponse::class, $label->onEmpty);
        self::assertSame("'missing'", $label->onEmpty->expression->spelling());
        self::assertSame(['n', 'label', 'child', 'value'], array_column($relation->outputs, 'name'));
        self::assertSame(\SqlSemantics\Type\Nullability::MaybeNull, $relation->outputs[2]->expression->nullability);
        self::assertSame('nested', $relation->withScope('nested')->scopeId);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testResultExpressionsRetainXmlPathsDefaultsAndNamespaces(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT x.* FROM XMLTABLE (XMLNAMESPACES ('urn:x' AS x), '/x:rows/x:row' PASSING BY REF '<rows/>' BY VALUE COLUMNS n FOR ORDINALITY, value INTEGER PATH '@id' DEFAULT 1 NOT NULL) AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        $relation = $statement->from;
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\XmlTable::class, $relation->table);
        self::assertSame('x', $relation->table->namespaces[0]->prefix);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\ValueColumn::class, $relation->table->columns[1]);
        self::assertSame("'@id'", $relation->table->columns[1]->path?->spelling());
        self::assertSame('1', $relation->table->columns[1]->default?->spelling());
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $relation->resultExpressions()[1]->nullability);
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
