<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Utility\Explain\OptionRule;

#[CoversClass(OptionRule::class)]
#[Medium]
final class OptionRuleTest extends TestCase
{
    public function testOptionsLowersAnalyze(): void
    {
        self::assertSame('EXPLAIN ANALYZE SELECT 1', (new Semantics(Dialect::MySql))->analyze('explain analyze select 1')->toString());
    }

    public function testFormatLowersTheName(): void
    {
        self::assertSame('EXPLAIN FORMAT = tree SELECT 1', (new Semantics(Dialect::MySql))->analyze('explain format = tree select 1')->toString());
    }

    public function testIntoLowersTheVariable(): void
    {
        self::assertSame('EXPLAIN FORMAT = json INTO @x SELECT 1', (new Semantics(Dialect::MySql))->analyze('explain format = json into @x select 1')->toString());
    }

    public function testDatabaseLowersTheDatabase(): void
    {
        self::assertSame('EXPLAIN FOR DATABASE `d b` DELETE FROM t', (new Semantics(Dialect::MySql))->analyze('explain for database `d b` delete from t')->toString());
    }
    public function testEmptyIntoRetainsTheIgnoredDestinationInTheIntermediateReleases(): void
    {
        self::assertSame("EXPLAIN INTO @'' SELECT 1", (new Semantics(Dialect::MySql, 'mysql-8.2.0'))->analyze("EXPLAIN INTO @'' SELECT 1")->toString());
        self::assertSame("EXPLAIN FORMAT = JSON INTO @'' SELECT 1", (new Semantics(Dialect::MySql, 'mysql-8.3.0'))->analyze("EXPLAIN FORMAT=JSON INTO @'' SELECT 1")->toString());
    }

    public function testDatabaseNameRefusesAnOversizedOperandAsANameDiagnostic(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Identifier name');
        (new Semantics(Dialect::MySql))->analyze('EXPLAIN FOR SCHEMA `' . str_repeat('a', 65) . '` SELECT 1');
    }

}
