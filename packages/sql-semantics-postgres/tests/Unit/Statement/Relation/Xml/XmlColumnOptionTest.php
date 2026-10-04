<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlColumnOption::class)]
#[Medium]
final class XmlColumnOptionTest extends TestCase
{
    public function testRenderWritesANamedOptionAsAnIdentifier(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text foo 'a')");
        self::assertSame("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text foo 'a')", $query->toString());
    }

    public function testRenderKeepsANamedOptionSpelledLikeAKeywordQuoted(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text \"path\" 'a', b text \"default\" 'b')");
        self::assertSame("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS a text \"path\" 'a', b text \"default\" 'b')", $query->toString());
    }
}
