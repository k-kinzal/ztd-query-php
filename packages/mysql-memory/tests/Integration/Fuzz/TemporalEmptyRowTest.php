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
final class TemporalEmptyRowTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (["STR_TO_DATE('x','%Y')", 'CAST(NULL AS DATE)', "CAST('x' AS DATE)", 'DATE(NULL)', "DATE('x')", "STR_TO_DATE('2020','%Y')"] as $expression) {
            yield 'single ' . $expression => ['SELECT (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (NULL,NULL) AS n,(SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (0,NULL) AS z; SHOW WARNINGS'];
            yield 'repeated ' . $expression => ['SELECT id,(SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (NULL,NULL) AS n,(SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (0,NULL) AS z FROM t2 ORDER BY id; SHOW WARNINGS'];
        }
        foreach (["CAST('2020-01-02' AS DATE)", "DATE('2020-01-02')", "STR_TO_DATE('2020-01-02','%Y-%m-%d')"] as $expression) {
            yield 'matching conversions ' . $expression => ['SELECT id,(SELECT ' . $expression . ",NULL FROM DUAL WHERE 0)<=> (CAST('2020-01-02' AS DATE),NULL) AS d,(SELECT " . $expression . ",NULL FROM DUAL WHERE 0)<=> ('2020-01-02',NULL) AS s,(SELECT " . $expression . ',NULL FROM DUAL WHERE 0)<=> (20200102,NULL) AS i FROM t2 ORDER BY id; SHOW WARNINGS'];
            yield 'later slot ' . $expression => ['SELECT id,(SELECT NULL,' . $expression . ',NULL FROM DUAL WHERE 0)<=> (NULL,NULL,NULL) AS n,(SELECT NULL,' . $expression . ",NULL FROM DUAL WHERE 0)<=> (NULL,CAST('2020-01-02' AS DATE),NULL) AS d FROM t2 ORDER BY id; SHOW WARNINGS"];
        }
    }

    #[DataProvider('providerStatements')]
    public function testEmptyTemporalProjectionPreservesNullStateAndWarnings(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
