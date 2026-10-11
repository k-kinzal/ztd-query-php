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
final class EmptyRuntimeConversionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['t1.a' => 10, '(t1.a)' => 10, '+t1.a' => 10, 't1.a+1' => 11, 'CAST(t1.a AS DECIMAL(12,2))' => 10, 't1.a+0e0' => 10, 'CONCAT(t1.a)' => 10, 'COALESCE(t1.a,0)' => 10] as $expression => $expected) {
            foreach (['', ' WHERE id>=3'] as $filter) {
                yield $expression . $filter => ['SELECT id,(SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (' . $expected . ',NULL) AS i,(SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (' . $expected . '.0,NULL) AS d,(SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (' . $expected . 'e0,NULL) AS r,(SELECT ' . $expression . ",NULL FROM DUAL WHERE 0)<=> ('" . $expected . "',NULL) AS s FROM t1" . $filter . ' ORDER BY id'];
                yield 'alias ' . $expression . $filter => ['SELECT id,' . $expression . ' AS v,(SELECT v,NULL FROM DUAL WHERE 0)<=> (' . $expected . ',NULL) AS i,(SELECT v,NULL FROM DUAL WHERE 0)<=> (' . $expected . '.0,NULL) AS d,(SELECT v,NULL FROM DUAL WHERE 0)<=> (' . $expected . 'e0,NULL) AS r,(SELECT v,NULL FROM DUAL WHERE 0)<=> (\'' . $expected . '\',NULL) AS s FROM t1' . $filter . ' ORDER BY id'];
            }
        }
        foreach (['@v', '(@v)', '+@v', '{fn @v}', '@v:=@v+1', '(@v:=@v+1)', '+(@v:=@v+1)', '{fn (@v:=@v+1)}'] as $expression) {
            foreach (['NULL', '1'] as $operand) {
                yield $expression . ' compared with ' . $operand => ['SET @v=1; SELECT id,(SELECT ' . $expression . ',NULL FROM DUAL WHERE 0)<=> (' . $operand . ',NULL) AS n,@v FROM t2 ORDER BY id'];
            }
        }
    }

    #[DataProvider('providerStatements')]
    public function testRuntimeSourcesPreserveConversionsAndAssignments(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
