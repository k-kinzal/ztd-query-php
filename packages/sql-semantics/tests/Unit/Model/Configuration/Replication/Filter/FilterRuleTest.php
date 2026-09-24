<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\FilterRule;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(FilterRule::class)]
#[Medium]
final class FilterRuleTest extends TestCase
{
    public function testEveryRuleIsSpelledAsItsKeyword(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER replicate_ignore_db = ()');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertSame(FilterRule::IgnoreDatabase, $statement->filters[0]->rule());
        self::assertSame('REPLICATE_REWRITE_DB', FilterRule::RewriteDatabase->value);
    }
}
