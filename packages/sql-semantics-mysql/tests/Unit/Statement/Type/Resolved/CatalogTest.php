<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Type\Resolved;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Catalog;

#[CoversClass(Catalog::class)]
#[Small]
final class CatalogTest extends TestCase
{
    public function testCanonicalSpellsUtf8AsUtf8mb3(): void
    {
        self::assertSame('utf8mb3', Catalog::canonical('UTF8'));
        self::assertSame('utf8mb3_bin', Catalog::canonical('utf8_bin'));
        self::assertSame('utf8mb4_bin', Catalog::canonical('utf8mb4_bin'));
    }

    public function testCanonicalNamesShareOneObjectAcrossReleases(): void
    {
        $catalog = new Catalog([
            'mysql-5.7.44' => ['charsets' => ['utf8' => ['utf8_general_ci', 3]], 'collations' => ['utf8_general_ci' => ['utf8', 33, true]]],
            'mysql-8.4.7' => ['charsets' => ['utf8mb3' => ['utf8mb3_general_ci', 3]], 'collations' => ['utf8mb3_general_ci' => ['utf8mb3', 33, true]]],
        ]);

        self::assertSame(['utf8mb3'], array_keys($catalog->charsets));
        self::assertSame(['utf8mb3_general_ci' => ['mysql-5.7.44', 'mysql-8.4.7']], $catalog->releases);
        self::assertSame($catalog->charsets['utf8mb3'], $catalog->collations['utf8mb3_general_ci']->charset);
        self::assertSame('utf8mb3_general_ci', $catalog->defaults['mysql-5.7.44']['utf8mb3']);
    }

    public function testSharedHoldsEveryRelease(): void
    {
        $catalog = Catalog::shared();

        self::assertSame($catalog, Catalog::shared());
        self::assertCount(9, $catalog->defaults);
        self::assertSame('utf8mb4_general_ci', $catalog->defaults['mysql-5.7.44']['utf8mb4']);
        self::assertSame('utf8mb4_0900_ai_ci', $catalog->defaults['mysql-8.4.7']['utf8mb4']);
    }
}
