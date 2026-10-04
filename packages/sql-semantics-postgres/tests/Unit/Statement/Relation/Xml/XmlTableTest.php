<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTable::class)]
#[Small]
final class XmlTableTest extends TestCase
{
    public function testDeriveRelationHasTheColumns(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE (XMLNAMESPACES ('u' AS x, DEFAULT 'v'), '/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' DEFAULT 'z' NOT NULL, b int) AS x");
        self::assertSame(['n:NotNull', 'a:NotNull', 'b:Nullable'], array_map(static fn (\SqlSemantics\Statement\Shape\Field $field): string => ($field->name->value ?? '') . ':' . $field->nullability->name, $query->fields()->items ?? []));
    }

    public function testDeriveRelationReportsAnUnrecognizedOption(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text foo 'a')");
        self::assertSame('unrecognized column option "foo"', $query->facts->diagnostics[0]->message());
    }

    public function testRenderWritesTheNamespacesAndColumns(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM LATERAL XMLTABLE ('/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY)");
        self::assertSame("SELECT * FROM LATERAL XMLTABLE ('/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY)", $query->toString());
    }
}
