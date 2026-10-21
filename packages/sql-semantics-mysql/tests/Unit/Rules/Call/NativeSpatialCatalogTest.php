<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Call\NativeSpatialCatalog;
use SqlSemantics\Statement\Type\Nullability;

#[CoversClass(NativeSpatialCatalog::class)]
#[Small]
final class NativeSpatialCatalogTest extends TestCase
{
    public function testRowsRefuseASetterArgumentForLegacySrid(): void
    {
        self::assertNull((new \SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions())->row(\SqlSemantics\Contract\GrammarRelease::MySql5744, 'SRID', 2));
    }

    public function testRowsPropagateNullabilityForModernSridReading(): void
    {
        $query = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("SELECT ST_SRID(X'00'),ST_SRID(NULL),ST_SRID(X'00',0)");
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze("SELECT ST_SRID(X'00')");

        self::assertSame([Nullability::NotNull, Nullability::Nullable, Nullability::Nullable, Nullability::Nullable], [$query->field(0)->nullability, $query->field(1)->nullability, $query->field(2)->nullability, $legacy->field(0)->nullability]);
    }

    public function testRowsKeepCoordinateReadersDistinctFromSettersAndLegacyAliases(): void
    {
        $query = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze("SELECT ST_X(X'00'),ST_X(NULL),ST_X(X'00',0)");
        self::assertSame([Nullability::NotNull, Nullability::Nullable, Nullability::Nullable], [$query->field(0)->nullability, $query->field(1)->nullability, $query->field(2)->nullability]);
        self::assertNull((new \SqlSemantics\Platform\MySql\Rules\Call\NativeFunctions())->row(\SqlSemantics\Contract\GrammarRelease::MySql5744, 'X', 2));
    }

    public function testRowsHoldAReleaseMaskArgumentCountsAndAResultCode(): void
    {
        self::assertCount(135, NativeSpatialCatalog::ROWS);
        self::assertSame([[3, 1, 1, 'DY'], [508, 1, 1, 'DP'], [508, 2, 2, 'GY']], NativeSpatialCatalog::ROWS['ST_X']);
        self::assertContainsOnly('array', NativeSpatialCatalog::ROWS);
    }
}
