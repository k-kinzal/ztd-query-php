<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Dependencies;
use SqlSemantics\Model\TableFunction\Json\JsonTable;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Dependencies::class)]
#[Medium]
final class DependenciesTest extends TestCase
{
    public function testOfCollectsJsonDocumentPathsVariablesAndDefaultsInDeclarationOrder(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' PASSING 2 AS k COLUMNS (n FOR ORDINALITY, label TEXT PATH '$.name' DEFAULT 'missing' ON EMPTY DEFAULT 'bad' ON ERROR, ok BOOLEAN EXISTS PATH '$.a', NESTED PATH '$.children[*]' COLUMNS (value INTEGER PATH '$'))) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        $spellings = array_map(static fn (Expression $expression): ?string => $expression->spelling(), Dependencies::of($statement->from->table));
        self::assertSame(["'[]'", "'$[*]'", '2', "'$.name'", "'bad'", "'missing'", "'$.a'", "'$.children[*]'", "'$'"], $spellings);
    }

    public function testOfCollectsXmlDocumentRowPathNamespacesAndColumnOperands(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT x.* FROM XMLTABLE (XMLNAMESPACES ('urn:x' AS x, DEFAULT 'urn:d'), '/x:rows/x:row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY, value INTEGER PATH '@id' DEFAULT 1) AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(XmlTable::class, $statement->from->table);
        $spellings = array_map(static fn (Expression $expression): ?string => $expression->spelling(), Dependencies::of($statement->from->table));
        self::assertSame(["'<rows/>'", "'/x:rows/x:row'", "'urn:x'", "'urn:d'", "'@id'", '1'], $spellings);
    }

    public function testJsonSkipsOrdinalColumnsAndColumnsWithoutAPath(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind("SELECT j.* FROM JSON_TABLE ('[]', '$[*]' COLUMNS (n FOR ORDINALITY, v INTEGER)) AS j");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(JsonTable::class, $statement->from->table);
        self::assertSame([], Dependencies::json($statement->from->table->columns));
    }
}
