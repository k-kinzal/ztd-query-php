<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract\Lookup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\Lookup\StockLookup;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\QualifiedName;

#[CoversClass(StockLookup::class)]
#[Small]
final class StockLookupTest extends TestCase
{
    public function testFirstCatalogSkipsOnlyTheCurrentTemporaryAlias(): void
    {
        self::assertTrue(StockLookup::firstCatalog([new Name('pg_temp'), new Name('pg_catalog'), new Name('other')]));
        self::assertFalse(StockLookup::firstCatalog([new Name('pg_temp'), new Name('other'), new Name('pg_catalog')]));
        self::assertFalse(StockLookup::firstCatalog([new Name('pg_temp_9'), new Name('pg_catalog')]));
    }

    public function testMatchesRequiresTheExactStockNameAndLookupScope(): void
    {
        $bare = new QualifiedName(new Name('C'));
        $qualified = new QualifiedName(new Name('C'), new Name('pg_catalog'));
        $different = new QualifiedName(new Name('C'), new Name('other'));

        self::assertTrue(StockLookup::matches($bare, 'C', true));
        self::assertFalse(StockLookup::matches($bare, 'C', false));
        self::assertTrue(StockLookup::matches($qualified, 'C', false));
        self::assertFalse(StockLookup::matches($different, 'C', true));
        self::assertFalse(StockLookup::matches($qualified, 'c', true));
    }
}
