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
final class UnicodeDistinctTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        $setup = "CREATE TABLE unicode_distinct (id INT PRIMARY KEY,v VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci); INSERT INTO unicode_distinct VALUES (0,'a'),(1,_utf8mb4 X'612000'),(2,'a '),(3,_utf8mb4 X'6100'),(4,NULL); ";
        foreach (['scan' => '', 'index' => 'ALTER TABLE unicode_distinct ADD KEY(v); '] as $access => $index) {
            foreach (['plain' => 'DISTINCT', 'small' => 'SQL_SMALL_RESULT DISTINCT', 'big' => 'SQL_BIG_RESULT DISTINCT'] as $name => $options) {
                yield $access . ' ' . $name => [$setup . $index . 'SELECT COUNT(*) FROM (SELECT ' . $options . ' v FROM unicode_distinct) d'];
                yield $access . ' ordered ' . $name => [$setup . $index . 'SELECT COUNT(*) FROM (SELECT ' . $options . ' v FROM unicode_distinct ORDER BY v) d'];
            }
        }
        foreach (['GROUP BY', 'ORDER BY'] as $scope) {
            yield 'ignored ' . $scope => [$setup . 'ALTER TABLE unicode_distinct ADD KEY(v); SELECT COUNT(*) FROM (SELECT DISTINCT v FROM unicode_distinct IGNORE INDEX FOR ' . $scope . '(v)) d'];
            yield 'ordered ignored ' . $scope => [$setup . 'ALTER TABLE unicode_distinct ADD KEY(v); SELECT COUNT(*) FROM (SELECT DISTINCT v FROM unicode_distinct IGNORE INDEX FOR ' . $scope . '(v) ORDER BY v) d'];
        }
        yield 'unsampled ignored index' => [str_replace('COLLATE utf8mb4_unicode_520_ci)', 'COLLATE utf8mb4_unicode_520_ci,KEY(v))', $setup) . 'SELECT COUNT(*) FROM (SELECT DISTINCT v FROM unicode_distinct IGNORE INDEX FOR GROUP BY(v)) d'];
        yield 'analyzed ignored index' => [str_replace('COLLATE utf8mb4_unicode_520_ci)', 'COLLATE utf8mb4_unicode_520_ci,KEY(v))', $setup) . 'ANALYZE TABLE unicode_distinct; SELECT COUNT(*) FROM (SELECT DISTINCT v FROM unicode_distinct IGNORE INDEX FOR GROUP BY(v)) d'];
        yield 'temporary ignored index' => [str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $setup) . 'ALTER TABLE unicode_distinct ADD KEY(v); SELECT COUNT(*) FROM (SELECT DISTINCT v FROM unicode_distinct IGNORE INDEX FOR GROUP BY(v)) d'];
    }

    #[DataProvider('providerStatements')]
    public function testUnicodeDistinctMatchesTheServer(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
