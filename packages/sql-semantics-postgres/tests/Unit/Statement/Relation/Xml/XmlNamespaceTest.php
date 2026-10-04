<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlNamespace::class)]
#[Small]
final class XmlNamespaceTest extends TestCase
{
    public function testRenderWritesThePrefixOrDefault(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE (XMLNAMESPACES ('u' AS x, DEFAULT 'v'), '/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' DEFAULT 'z' NOT NULL, b int) AS x");
        self::assertSame("SELECT * FROM XMLTABLE (XMLNAMESPACES ('u' AS x, DEFAULT 'v'), '/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text PATH 'a' DEFAULT 'z' NOT NULL, b INT) AS x", $query->toString());
    }
}
