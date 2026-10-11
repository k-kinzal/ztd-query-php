<?php

declare(strict_types=1);

namespace Tests\Integration\Fuzz;

use Fuzz\Target\Servers;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Large]
final class SetEmptyRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $queries = [
            'except nullable final nonnull' => '(SELECT NULL,NULL UNION ALL SELECT 1,NULL) EXCEPT (SELECT NULL,NULL UNION ALL SELECT 1,NULL)',
            'except nullable final null' => '(SELECT 1,NULL UNION ALL SELECT NULL,NULL) EXCEPT (SELECT NULL,NULL UNION ALL SELECT 1,NULL)',
            'intersect nullable empty' => '(SELECT NULL,NULL UNION ALL SELECT 1,NULL) INTERSECT SELECT 2,NULL',
            'empty nullable column' => 'SELECT a,NULL FROM t1 WHERE 0 UNION ALL SELECT a,NULL FROM t1 WHERE 0',
            'empty nonnull column' => 'SELECT id,NULL FROM t1 WHERE 0 UNION ALL SELECT id,NULL FROM t1 WHERE 0',
            'nullable filtered' => 'SELECT a,NULL FROM t1 WHERE a IS NOT NULL AND id=99 UNION ALL SELECT 1,NULL FROM DUAL WHERE 0',
            'offset sorted descending' => 'SELECT NULL a,NULL b UNION ALL SELECT 1,NULL ORDER BY a DESC LIMIT 1 OFFSET 2',
            'offset sorted ascending' => 'SELECT NULL a,NULL b UNION ALL SELECT 1,NULL ORDER BY a ASC LIMIT 1 OFFSET 2',
            'offset sorted all' => 'SELECT 1 a,NULL b UNION ALL SELECT 2,NULL ORDER BY a DESC LIMIT 1 OFFSET 2',
            'zero offset effects' => 'SELECT 1/0,NULL UNION ALL SELECT 1/0,NULL LIMIT 0 OFFSET 2',
            'zero effects' => 'SELECT 1/0,NULL UNION ALL SELECT 1/0,NULL LIMIT 0',
            'nonnull' => 'SELECT 1,2 FROM DUAL WHERE 0 UNION ALL SELECT 3,4 FROM DUAL WHERE 0',
            'nulls' => 'SELECT NULL,NULL FROM DUAL WHERE 0 UNION ALL SELECT NULL,NULL FROM DUAL WHERE 0',
            'first null' => 'SELECT NULL,1 FROM DUAL WHERE 0 UNION ALL SELECT NULL,2 FROM DUAL WHERE 0',
            'last null' => 'SELECT 1,NULL FROM DUAL WHERE 0 UNION ALL SELECT 2,NULL FROM DUAL WHERE 0',
            'mixed' => 'SELECT 1,NULL FROM DUAL WHERE 0 UNION ALL SELECT NULL,NULL FROM DUAL WHERE 0',
            'reversed mixed' => 'SELECT NULL,NULL FROM DUAL WHERE 0 UNION ALL SELECT 1,NULL FROM DUAL WHERE 0',
            'null integer' => 'SELECT CAST(NULL AS SIGNED),NULL FROM DUAL WHERE 0 UNION ALL SELECT CAST(NULL AS SIGNED),NULL FROM DUAL WHERE 0',
            'null string' => 'SELECT CAST(NULL AS CHAR),NULL FROM DUAL WHERE 0 UNION ALL SELECT CAST(NULL AS CHAR),NULL FROM DUAL WHERE 0',
            'null date' => 'SELECT CAST(NULL AS DATE),NULL FROM DUAL WHERE 0 UNION ALL SELECT CAST(NULL AS DATE),NULL FROM DUAL WHERE 0',
            'null real' => 'SELECT NULL+1e0,NULL FROM DUAL WHERE 0 UNION ALL SELECT NULL+1e0,NULL FROM DUAL WHERE 0',
            'warn' => 'SELECT 1/0,NULL FROM DUAL WHERE 0 UNION ALL SELECT 1/0,NULL FROM DUAL WHERE 0',
            'offset null' => 'SELECT NULL,NULL UNION ALL SELECT NULL,NULL LIMIT 1 OFFSET 2',
            'offset nonnull' => 'SELECT 1,2 UNION ALL SELECT 3,4 LIMIT 1 OFFSET 2',
            'offset last null' => 'SELECT 1,NULL UNION ALL SELECT 2,NULL LIMIT 1 OFFSET 2',
            'offset final null' => 'SELECT 1,2 UNION ALL SELECT NULL,NULL LIMIT 1 OFFSET 2',
            'offset first null' => 'SELECT NULL,NULL UNION ALL SELECT 1,2 LIMIT 1 OFFSET 2',
            'zero limit' => 'SELECT NULL,NULL UNION ALL SELECT 1,NULL LIMIT 0',
            'zero limit null type' => 'SELECT NULL,NULL UNION ALL SELECT NULL,NULL LIMIT 0',
            'distinct last null' => 'SELECT 1,NULL FROM DUAL WHERE 0 UNION SELECT 2,NULL FROM DUAL WHERE 0',
            'distinct offset last null' => 'SELECT 1,NULL UNION SELECT 2,NULL LIMIT 1 OFFSET 2',
            'distinct offset first null' => 'SELECT NULL,NULL UNION SELECT 1,2 LIMIT 1 OFFSET 2',
            'offset table last null' => 'SELECT id,NULL FROM t1 WHERE id=1 UNION ALL SELECT id,NULL FROM t1 WHERE id=2 LIMIT 1 OFFSET 2',
            'offset locally bounded' => '(SELECT 1,NULL LIMIT 1) UNION ALL (SELECT 2,NULL LIMIT 1) LIMIT 1 OFFSET 2',
            'except' => 'SELECT 1,NULL EXCEPT SELECT 1,NULL',
            'intersect' => 'SELECT 1,NULL INTERSECT SELECT 2,NULL',
        ];
        foreach ($queries as $name => $query) {
            yield $name => ['SELECT (' . $query . ') <=> (NULL,NULL) AS nulls, (' . $query . ') <=> (0,NULL) AS zero_null, (' . $query . ') <=> (99,NULL) AS nonzero_null, (' . $query . ') <=> (NULL,0) AS null_zero, (' . $query . ') = (NULL,NULL) AS plain'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptySetProjectionMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
