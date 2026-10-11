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
final class EmptyBranchMetadataTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['1<=>NULL', '(1,NULL)<=> (NULL,NULL)', '(SELECT 1/0,NULL FROM DUAL WHERE 0)<=> (NULL,NULL)'] as $comparison) {
            foreach (['1', '123', '-123'] as $fallback) {
                yield 'case ' . $comparison . ' else ' . $fallback => ['SELECT CASE WHEN 0 THEN ' . $comparison . ' ELSE ' . $fallback . ' END AS v FROM t2'];
            }
            yield 'if ' . $comparison => ['SELECT IF(0,' . $comparison . ',1) AS v FROM t2'];
            yield 'coalesce ' . $comparison => ['SELECT COALESCE(' . $comparison . ',1) AS v FROM t2'];
            yield 'case selected ' . $comparison => ['SELECT CASE WHEN 1 THEN ' . $comparison . ' ELSE 1 END AS v FROM t2'];
        }
        yield 'integer branches' => ['SELECT CASE WHEN 0 THEN 0 ELSE 1 END AS v,CASE WHEN 0 THEN 1 ELSE 123 END AS w FROM t2'];
        yield 'simple case' => ['SELECT CASE 0 WHEN 1 THEN (SELECT 1/0,NULL FROM DUAL WHERE 0)<=> (NULL,NULL) ELSE 1 END AS v FROM t2'];
        foreach (['2', '9', '10', '-1', '1.0', '1e0', "'1'"] as $value) {
            $case = 'CASE WHEN 0 THEN 0 ELSE ' . $value . ' END';
            yield 'result bound ' . $value => ['SELECT ' . $case . ' AS v,(' . $case . ')+0 AS a,CAST((' . $case . ') AS CHAR) AS c,COALESCE((' . $case . '),1) AS f FROM t2'];
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptyComparisonBranchesPreserveMetadata(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
