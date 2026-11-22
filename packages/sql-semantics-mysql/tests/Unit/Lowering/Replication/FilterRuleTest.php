<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Lowering\Replication\FilterRule;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(FilterRule::class)]
#[Medium]
final class FilterRuleTest extends TestCase
{
    public function testFiltersLowersTheListInOrder(): void
    {
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_IGNORE_DB = (b), REPLICATE_DO_DB = (a)', (new Inspection())->inspect('change replication filter replicate_ignore_db = (b), replicate_do_db = (a)')->toString());
    }

    public function testValuesLowersPairsAndEmptyLists(): void
    {
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ((a, b), (c, d)), REPLICATE_REWRITE_DB = ()', (new Inspection())->inspect('change replication filter replicate_rewrite_db = ((a, b), (c, d)), replicate_rewrite_db = ()')->toString());
    }

    public function testValueLowersQualifiedTables(): void
    {
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE = (a.b)', (new Inspection('mysql-5.7.44'))->inspect('change replication filter replicate_ignore_table = (a.b)')->toString());
    }

    public function testDatabaseLowersTheName(): void
    {
        self::assertSame('CHANGE REPLICATION FILTER REPLICATE_DO_DB = (`a b`)', (new Inspection())->inspect('change replication filter replicate_do_db = (`a b`)')->toString());
    }

    public function testPatternLowersTheString(): void
    {
        self::assertSame("CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = ('a.%', 'b.c')", (new Inspection())->inspect("change replication filter replicate_wild_do_table = ('a.%', 'b.c')")->toString());
    }
}
