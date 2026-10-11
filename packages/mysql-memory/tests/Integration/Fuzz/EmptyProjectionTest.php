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
final class EmptyProjectionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['NULL', '1', 'SQRT(1)', 'SQRT(-1)', '1/0', 'NULL+1', 'CONCAT(NULL)', "CONCAT('x')", "CAST('x' AS SIGNED)", 'IFNULL(NULL,1)', "STR_TO_DATE('x','%Y')"] as $expression) {
            yield $expression => ['SELECT (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (NULL,NULL) AS n, (SELECT ' . $expression . ',NULL FROM DUAL WHERE 0) <=> (0,NULL) AS z'];
        }
        yield 'ordinary comparison skips expressions' => ['SELECT (SELECT 1/0,NULL FROM DUAL WHERE 0) = (NULL,NULL)'];
        yield 'cached empty result repeats warnings' => ['SELECT (SELECT 1/0,NULL FROM DUAL WHERE 0) <=> (NULL,NULL) FROM t2'];
        yield 'null mismatch skips later warning' => ['SELECT (SELECT NULL,1/0 FROM DUAL WHERE 0) <=> (1,NULL)'];
        yield 'last column null' => ['SELECT (SELECT 1,2,NULL FROM DUAL WHERE 0) <=> (9,8,NULL)'];
        yield 'middle column null' => ['SELECT (SELECT 1,NULL,2 FROM DUAL WHERE 0) <=> (9,NULL,8)'];
        yield 'first column null mismatch' => ['SELECT (SELECT NULL,1,NULL FROM DUAL WHERE 0) <=> (1,8,NULL)'];
        yield 'empty and present projection' => ['SELECT (SELECT 1,NULL FROM DUAL WHERE 0) <=> (SELECT 2,NULL)'];
        yield 'two empty projections' => ['SELECT (SELECT 1,NULL FROM DUAL WHERE 0) <=> (SELECT 2,NULL FROM DUAL WHERE 0)'];
        yield 'zero limit retains expressions' => ['SELECT (SELECT 1,NULL LIMIT 0) <=> (2,NULL), (SELECT NULL,NULL LIMIT 0) <=> (NULL,NULL)'];
        yield 'assignment' => ['SET @v=0; SELECT (SELECT @v:=@v+1,NULL FROM DUAL WHERE 0) <=> (1,NULL), @v'];
        yield 'assignment skipped by ordinary comparison' => ['SET @v=0; SELECT (SELECT @v:=@v+1,NULL FROM DUAL WHERE 0) = (1,NULL), @v'];
        yield 'assignment skipped after null mismatch' => ['SET @v=0; SELECT (SELECT NULL,@v:=@v+1 FROM DUAL WHERE 0) <=> (1,NULL), @v'];
        yield 'assignment repeats on each read' => ['SET @v=0; SELECT (SELECT @v:=@v+1,NULL FROM DUAL WHERE 0) <=> (1,NULL), @v FROM t2'];
    }

    #[DataProvider('providerStatements')]
    public function testProjectionMatchesMySql(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
