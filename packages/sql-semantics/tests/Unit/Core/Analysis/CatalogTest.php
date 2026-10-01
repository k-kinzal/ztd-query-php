<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use SqlSemantics\Semantic\Name;
use SqlSemantics\Semantic\QualifiedName;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Core\Analysis\Catalog::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class CatalogTest extends TestCase
{
    public function testFindDistinguishesAnEmptyCatalogFromAnUnprovidedCatalog(): void
    {
        $ids = new \SqlSemantics\Core\Ast\Identifiers(Dialect::Sqlite);
        $table = SemanticCases::table(Dialect::Sqlite);
        $catalog = new \SqlSemantics\Core\Analysis\Catalog($ids, [$table]);
        self::assertSame($table, $catalog->find($table->name));
        self::assertNull($catalog->find(new QualifiedName(new Name('absent'))));
        self::assertNull((new \SqlSemantics\Core\Analysis\Catalog($ids, null))->tables);
        self::assertSame([], (new \SqlSemantics\Core\Analysis\Catalog($ids, []))->tables);
    }
}
