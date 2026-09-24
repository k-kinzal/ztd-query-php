<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\FilterRule;
use SqlSemantics\Model\Configuration\Replication\Filter\RewriteFilter;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RewriteFilter::class)]
#[Medium]
final class RewriteFilterTest extends TestCase
{
    public function testRuleIsRewriteDatabase(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_REWRITE_DB = ()');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertEquals([new RewriteFilter([])], $statement->filters);
        self::assertSame(FilterRule::RewriteDatabase, $statement->filters[0]->rule());
    }
}
