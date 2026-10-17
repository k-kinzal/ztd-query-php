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
final class TransparentExpressionTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStatements(): iterable
    {
        foreach (['a', '(a)', '((a))', '+a', '(+a)', '{fn a}', 'a+0', 'DEFAULT(a)', '(DEFAULT(a))', '+DEFAULT(a)', '{fn DEFAULT(a)}', 'CAST(a AS SIGNED)', '(CAST(a AS SIGNED))'] as $expression) {
            yield $expression => ['SELECT ' . $expression . ' AS v FROM t1 ORDER BY id'];
        }
        yield 'default and actual value' => ['CREATE TABLE wrap_default(a INT DEFAULT 7); INSERT INTO wrap_default VALUES(99); SELECT DEFAULT(a) AS d,(DEFAULT(a)) AS g,+DEFAULT(a) AS u,{fn DEFAULT(a)} AS o,a AS actual FROM wrap_default'];
        yield 'nested default wrappers' => ['CREATE TABLE wrap_default(a INT DEFAULT 7); INSERT INTO wrap_default VALUES(99); SELECT (+{fn DEFAULT(a)}) AS d FROM wrap_default'];
        yield 'temporal ODBC conversion' => ["SELECT {d '2020-01-02'} AS d,({d '2020-01-02'}) AS g,{ts '2020-01-02 03:04:05'} AS t"];
        yield 'wrapped warning' => ["SET sql_mode='ERROR_FOR_DIVISION_BY_ZERO'; SELECT (1/0) AS g,+(1/0) AS u,{fn (1/0)} AS o"];
        yield 'wrapped assignment' => ['SET @v=0; SELECT (@v:=@v+1) AS g,+(@v:=@v+1) AS u,{fn (@v:=@v+1)} AS o,@v AS final'];
    }

    #[DataProvider('providerStatements')]
    public function testTransparentExpressionsPreserveValuesAndMetadata(string $sql): void
    {
        [$target] = Servers::shared();
        $comparison = $target->compare($sql);

        self::assertFalse($comparison->volatile, (string) $comparison->referenceDifference);
        self::assertNull($comparison->difference, (string) $comparison->difference);
    }
}
