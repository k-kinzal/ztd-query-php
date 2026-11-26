<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Contract\StockRoles;

#[CoversClass(StockRoles::class)]
final class StockRolesTest extends TestCase
{
    public function testContainsOnlyTheStockIdentitiesOfTheRelease(): void
    {
        self::assertTrue(StockRoles::contains(GrammarRelease::PostgreSql166, 'pg_checkpoint'));
        self::assertFalse(StockRoles::contains(GrammarRelease::PostgreSql166, 'pg_maintain'));
        self::assertTrue(StockRoles::contains(GrammarRelease::PostgreSql172, 'pg_maintain'));
        self::assertFalse(StockRoles::contains(GrammarRelease::PostgreSql172, 'postgres'));
    }
}
