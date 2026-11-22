<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Utility\Explain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Utility\Explain\OptionRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(OptionRule::class)]
#[Medium]
final class OptionRuleTest extends TestCase
{
    public function testOptionsLowersAnalyze(): void
    {
        self::assertSame('EXPLAIN ANALYZE SELECT 1', (new Inspection())->inspect('explain analyze select 1')->toString());
    }

    public function testFormatLowersTheName(): void
    {
        self::assertSame('EXPLAIN FORMAT = tree SELECT 1', (new Inspection())->inspect('explain format = tree select 1')->toString());
    }

    public function testIntoLowersTheVariable(): void
    {
        self::assertSame('EXPLAIN FORMAT = `json` INTO @x SELECT 1', (new Inspection())->inspect('explain format = json into @x select 1')->toString());
    }

    public function testDatabaseLowersTheDatabase(): void
    {
        self::assertSame('EXPLAIN FOR DATABASE `d b` DELETE FROM t', (new Inspection())->inspect('explain for database `d b` delete from t')->toString());
    }
}
