<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Relation\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTableColumn::class)]
#[Medium]
final class XmlTableColumnTest extends TestCase
{
    public function testOrdinalityTellsTheNumberingColumn(): void
    {
        self::assertSame([true, false], [(new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTableColumn(new \SqlSemantics\Statement\Identifier\Name('n')))->ordinality(), (new \SqlSemantics\Platform\PostgreSql\Statement\Relation\Xml\XmlTableColumn(new \SqlSemantics\Statement\Identifier\Name('a'), new \SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName(new \SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\KeywordDesignation(\SqlSemantics\Platform\PostgreSql\Statement\Type\Designation\TypeKeyword::Boolean))))->ordinality()]);
    }

    public function testRenderWritesTheTypeAndOptions(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text NULL)");
        self::assertSame("SELECT * FROM XMLTABLE ('/r' PASSING '<r/>' COLUMNS n FOR ORDINALITY, a text NULL)", $query->toString());
    }
}
