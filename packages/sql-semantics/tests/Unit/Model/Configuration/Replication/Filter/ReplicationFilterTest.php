<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\FilterRule;
use SqlSemantics\Model\Configuration\Replication\Filter\ReplicationFilter;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ReplicationFilter::class)]
#[Medium]
final class ReplicationFilterTest extends TestCase
{
    public function testRuleNamesEveryKindOfFilter(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_DO_DB = (a), REPLICATE_DO_TABLE = (a.t), REPLICATE_WILD_IGNORE_TABLE = ('a.%'), REPLICATE_REWRITE_DB = ((a, b))");
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertSame(
            [FilterRule::DoDatabase, FilterRule::DoTable, FilterRule::WildIgnoreTable, FilterRule::RewriteDatabase],
            array_map(static fn (ReplicationFilter $filter): FilterRule => $filter->rule(), $statement->filters),
        );
    }
}
