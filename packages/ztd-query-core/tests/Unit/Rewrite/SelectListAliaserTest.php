<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Rewrite\SelectListAliaser;

#[CoversClass(SelectListAliaser::class)]
final class SelectListAliaserTest extends TestCase
{
    public function testAliasesOnlyTopLevelProjectionAndPreservesClauses(): void
    {
        $quoter = new class () implements IdentifierQuoter {
            public function quote(string $identifier): string
            {
                return '`' . $identifier . '`';
            }
        };

        $result = (new SelectListAliaser())->alias(
            'WITH x AS (SELECT id FROM source) SELECT DISTINCT x.id AS old, COUNT(*) FROM x GROUP BY x.id WITH ROLLUP',
            $quoter,
        );

        self::assertSame(
            'WITH x AS (SELECT id FROM source) SELECT DISTINCT x.id AS `__ztd_insert_0`, COUNT(*) AS `__ztd_insert_1` FROM x GROUP BY x.id WITH ROLLUP',
            $result,
        );
    }

    public function testLeavesWildcardProjectionUnchanged(): void
    {
        $quoter = new class () implements IdentifierQuoter {
            public function quote(string $identifier): string
            {
                return '"' . $identifier . '"';
            }
        };
        $sql = 'SELECT source.* FROM source';

        self::assertSame($sql, (new SelectListAliaser())->alias($sql, $quoter));
    }
}
