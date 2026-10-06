<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Catalogs::class)]
#[Medium]
final class CatalogsTest extends TestCase
{
    public function testStatementDispatchesToTheGroup(): void
    {
        self::assertSame('ALTER COLLATION c REFRESH VERSION', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER COLLATION c REFRESH VERSION')->toString());
    }

    public function testEnumValuesLowersTheLabels(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TYPE t AS ENUM (\'a\', \'b\')');
        self::assertCount(2, (new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Catalogs($lowering))->enumValues($tree->find('opt_enum_val_list')[0]));
    }

    public function testEnumValuesOfAnEmptyList(): void
    {
        $lowering = new \SqlSemantics\Platform\PostgreSql\Lowering\Lowering((new \SqlSemantics\Platform\PostgreSql\Platform())->productions(new \SqlSemantics\Contract\LanguageProfile(\SqlSemantics\Contract\GrammarRelease::PostgreSql172)), new \SqlSemantics\Lowering\Leaves(), \SqlSemantics\Contract\GrammarRelease::PostgreSql172);
        $tree = (new \SqlParser\PostgreSql\PostgreSqlParser('pg-17.2'))->parse('CREATE TYPE t AS ENUM ()');
        self::assertSame([], (new \SqlSemantics\Platform\PostgreSql\Lowering\Catalog\Catalogs($lowering))->enumValues($tree->find('opt_enum_val_list')[0]));
    }
}
