<?php

declare(strict_types=1);

namespace Tests\Unit\Model\TableFunction\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\DocumentRelation;
use SqlSemantics\Model\TableFunction\Xml\NamespaceBinding;
use SqlSemantics\Model\TableFunction\Xml\XmlTable;
use SqlSemantics\SchemaBuilder;

#[CoversClass(NamespaceBinding::class)]
#[Medium]
final class NamespaceBindingTest extends TestCase
{
    public function testDistinguishesPrefixedFromDefaultNamespaces(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind("SELECT x.* FROM XMLTABLE (XMLNAMESPACES ('urn:x' AS x, DEFAULT 'urn:d'), '/x:rows/x:row' PASSING '<rows/>' COLUMNS n FOR ORDINALITY) AS x");
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(DocumentRelation::class, $statement->from);
        self::assertInstanceOf(XmlTable::class, $statement->from->table);
        $namespaces = $statement->from->table->namespaces;
        self::assertSame(['x', null], array_column($namespaces, 'prefix'));
        self::assertSame("'urn:x'", $namespaces[0]->uri->spelling());
        self::assertSame("'urn:d'", $namespaces[1]->uri->spelling());
        self::assertSame('SELECT "x"."n" AS "n" FROM XMLTABLE(XMLNAMESPACES(\'urn:x\' AS "x", DEFAULT \'urn:d\'), \'/x:rows/x:row\' PASSING \'<rows/>\' COLUMNS "n" FOR ORDINALITY) AS "x"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testIsUnprefixedByDefault(): void
    {
        $namespace = new NamespaceBinding(Expression::literal('urn:d', Dialect::PostgreSql));
        self::assertNull($namespace->prefix);
        self::assertSame("'urn:d'", $namespace->uri->spelling());
    }
}
