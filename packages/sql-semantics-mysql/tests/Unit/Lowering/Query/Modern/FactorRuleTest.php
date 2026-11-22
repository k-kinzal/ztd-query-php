<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Query\Modern;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Query\Legacy\FactorRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FactorRule::class)]
#[Medium]
final class FactorRuleTest extends TestCase
{
    public function testFactorLowersEveryFactor(): void
    {
        self::assertSame('SELECT 1 FROM t, (SELECT 1) d, JSON_TABLE(\'[]\', \'$\' COLUMNS (a INT PATH \'$\')) j, (t)', (new Inspection('mysql-8.4.7'))->inspect('select 1 from t, (select 1) d, json_table(\'[]\', \'$\' columns (a int path \'$\')) j, (t)')->toString());
    }

    public function testParensLowersEveryParenthesizedForm(): void
    {
        self::assertSame('SELECT 1 FROM ((t)), ((t JOIN u)), ((t, u))', (new Inspection('mysql-8.4.7'))->inspect('select 1 from ((t)), ((t join u)), ((t, u))')->toString());
    }

    public function testDerivedLowersLateral(): void
    {
        self::assertSame('SELECT 1 FROM t, LATERAL (SELECT 1) AS d', (new Inspection('mysql-8.0.44'))->inspect('select 1 from t, lateral (select 1) as d')->toString());
    }

    public function testFunctionLowersJsonTable(): void
    {
        self::assertSame('SELECT 1 FROM JSON_TABLE(@j, \'$\' COLUMNS (a INT PATH \'$\')) AS j', (new Inspection('mysql-9.1.0'))->inspect('select 1 from json_table(@j, \'$\' columns (a int path \'$\')) as j')->toString());
    }
}
