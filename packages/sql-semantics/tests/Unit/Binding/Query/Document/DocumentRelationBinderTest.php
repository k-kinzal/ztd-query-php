<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\Document\DocumentRelationBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DocumentRelationBinder::class)]
#[Medium]
final class DocumentRelationBinderTest extends TestCase
{
    public function testBindGivesJsonTableItsOwnRelationWithAliasedOutputs(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT jt.x FROM t, JSON_TABLE(t.doc, '$[*]' COLUMNS (id INT PATH '$.id', n FOR ORDINALITY)) AS jt(x, y)");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertSame([], $statement->diagnostics);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertSame('jt', $relation->alias);
        self::assertSame(['x', 'y'], $relation->columnAliases);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Json\JsonTable::class, $relation->table);
        self::assertSame(['x', 'y'], array_column($relation->declaration->columns, 'name'));
        self::assertSame('r1', $relation->id);
    }

    public function testBindGivesXmlTableARelationInTheSameScope(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(x XML)')))->bind("SELECT c.a FROM t, XMLTABLE('/r' PASSING t.x COLUMNS a INT PATH 'a') AS c");
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        $relation = $statement->relations[1];
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $relation);
        self::assertInstanceOf(\SqlSemantics\Model\TableFunction\Xml\XmlTable::class, $relation->table);
        self::assertSame('c', $relation->alias);
        self::assertSame('a', $statement->outputs[0]->expression->columnBinding()?->column->name);
    }

    public function testBindResolvesTheDocumentAgainstPrecedingRelationsOnly(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind("SELECT * FROM JSON_TABLE(t.doc, '$' COLUMNS (id INT PATH '$.id')), t", strict: false);
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\DocumentRelation::class, $statement->relations[0]);
        self::assertSame('unknown-column', $statement->diagnostics[0]->reason);
    }

    public function testBindLeavesOrdinaryTableFunctionsAlone(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(doc JSON)')))->bind('SELECT * FROM t, generate_series(1, 3) AS g');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Relation\FunctionRelation::class, $statement->relations[1]);
    }
}
