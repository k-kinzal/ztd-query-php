<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\IdentifierQuoter;
use ZtdQuery\Rewrite\InsertSelectProjector;

#[CoversClass(InsertSelectProjector::class)]
final class InsertSelectProjectorTest extends TestCase
{
    public function testAssignsSourceColumnsByPositionAndCompletesTargetShape(): void
    {
        $quoter = new class () implements IdentifierQuoter {
            public function quote(string $identifier): string
            {
                return '"' . $identifier . '"';
            }
        };

        $sql = (new InsertSelectProjector($quoter))->project(
            'SELECT DISTINCT name, COUNT(*) FROM users GROUP BY name',
            ['id', 'name', 'count', 'status'],
            ['name', 'count'],
            ['status' => "'active'"],
            ['id' => 'ROW_NUMBER() OVER ()'],
        );

        self::assertSame(
            'WITH "__ztd_insert_source" ("__ztd_insert_0", "__ztd_insert_1") AS ('
            . 'SELECT DISTINCT name, COUNT(*) FROM users GROUP BY name) '
            . 'SELECT ROW_NUMBER() OVER () AS "id", "__ztd_insert_0" AS "name", '
            . '"__ztd_insert_1" AS "count", \'active\' AS "status" FROM "__ztd_insert_source"',
            $sql,
        );
    }
}
