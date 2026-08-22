<?php

declare(strict_types=1);

namespace Tests\Unit\Transformer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Transformer\MySqlSelectListAliaser;

#[CoversClass(MySqlSelectListAliaser::class)]
#[UsesClass(MySqlIdentifierQuoter::class)]
#[UsesClass(\ZtdQuery\Platform\MySql\MySqlLexerProfile::class)]
final class MySqlSelectListAliaserTest extends TestCase
{
    public function testAliasesOnlyTopLevelProjectionAndPreservesClauses(): void
    {
        $result = (new MySqlSelectListAliaser())->alias(
            'WITH x AS (SELECT id FROM source) SELECT DISTINCT x.id AS old, COUNT(*) FROM x GROUP BY x.id WITH ROLLUP',
        );

        self::assertSame(
            'WITH x AS (SELECT id FROM source) SELECT DISTINCT x.id AS `__ztd_insert_0`, COUNT(*) AS `__ztd_insert_1` FROM x GROUP BY x.id WITH ROLLUP',
            $result,
        );
    }

    public function testLeavesWildcardProjectionUnchanged(): void
    {
        $aliaser = new MySqlSelectListAliaser();

        self::assertSame('SELECT source.* FROM source', $aliaser->alias('SELECT source.* FROM source'));
        self::assertSame('SELECT id, source.* FROM source', $aliaser->alias('SELECT id, source.* FROM source'));
    }

    public function testIgnoresHashCommentsWhenAliasingMySqlProjection(): void
    {
        $sql = "SELECT # FROM ignored\n id, name FROM users";

        self::assertSame(
            "SELECT # FROM ignored\n id AS `__ztd_insert_0`, name AS `__ztd_insert_1` FROM users",
            (new MySqlSelectListAliaser())->alias($sql),
        );
    }

    public function testCountsStructuredProjectionWithoutTreatingFunctionArgumentAsWildcard(): void
    {
        $aliaser = new MySqlSelectListAliaser();

        self::assertSame(2, $aliaser->projectionCount('SELECT name, COUNT(*) FROM users GROUP BY name'));
        self::assertNull($aliaser->projectionCount('SELECT * FROM users'));
        self::assertNull($aliaser->projectionCount('SELECT users.* FROM users'));
        self::assertNull($aliaser->projectionCount('VALUES (1)'));
        self::assertNull($aliaser->projectionCount('SELECT'));
    }

    public function testRecognizesEveryMySqlSelectListTerminator(): void
    {
        $aliaser = new MySqlSelectListAliaser();

        self::assertStringContainsString('value AS `__ztd_insert_0` WHERE', $aliaser->alias('SELECT value WHERE enabled = 1'));
        self::assertStringContainsString('value AS `__ztd_insert_0` GROUP', $aliaser->alias('SELECT value GROUP BY value'));
        self::assertStringContainsString('value AS `__ztd_insert_0` HAVING', $aliaser->alias('SELECT value HAVING value > 0'));
        self::assertStringContainsString('value AS `__ztd_insert_0` ORDER', $aliaser->alias('SELECT value ORDER BY value'));
        self::assertStringContainsString('value AS `__ztd_insert_0` LIMIT', $aliaser->alias('SELECT value LIMIT 1'));
        self::assertStringContainsString('value AS `__ztd_insert_0` UNION', $aliaser->alias('SELECT value UNION SELECT other'));
        self::assertStringContainsString('value AS `__ztd_insert_0` INTERSECT', $aliaser->alias('SELECT value INTERSECT SELECT other'));
        self::assertStringContainsString('value AS `__ztd_insert_0` EXCEPT', $aliaser->alias('SELECT value EXCEPT SELECT other'));
    }

    public function testPreservesMySqlModifiersAndTrimsTheirExpression(): void
    {
        $aliaser = new MySqlSelectListAliaser();

        self::assertSame(
            'SELECT distinct sql_no_cache value AS `__ztd_insert_0` FROM source',
            $aliaser->alias('SELECT distinct sql_no_cache   value FROM source'),
        );
        self::assertSame(
            'SELECT value DISTINCT AS `__ztd_insert_0` FROM source',
            $aliaser->alias('SELECT value DISTINCT FROM source'),
        );
    }

    public function testLeavesStatementsWithoutSelectAndMalformedEmptyProjectionUnchanged(): void
    {
        $aliaser = new MySqlSelectListAliaser();

        self::assertSame('VALUES (1)', $aliaser->alias('VALUES (1)'));
        self::assertSame('SELECT , value FROM source', $aliaser->alias('SELECT , value FROM source'));
    }

    public function testRemovesOnlyTheLastCompleteExplicitAlias(): void
    {
        $aliaser = new MySqlSelectListAliaser();

        self::assertSame(
            'SELECT value AS AS `__ztd_insert_0` FROM source',
            $aliaser->alias('SELECT value AS FROM source'),
        );
        self::assertSame(
            'SELECT value AS first AS `__ztd_insert_0` FROM source',
            $aliaser->alias('SELECT value AS first AS second FROM source'),
        );
    }
}
