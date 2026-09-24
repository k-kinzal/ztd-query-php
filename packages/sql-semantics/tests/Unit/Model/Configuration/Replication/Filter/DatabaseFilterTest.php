<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\DatabaseFilter;
use SqlSemantics\Model\Configuration\Replication\Filter\FilterRule;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DatabaseFilter::class)]
#[Medium]
final class DatabaseFilterTest extends TestCase
{
    public function testRuleReturnsTheDatabaseRule(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_DO_DB = (Sales, `a``b`)');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertEquals([new DatabaseFilter(FilterRule::DoDatabase, ['Sales', 'a`b'])], $statement->filters);
        self::assertSame(FilterRule::DoDatabase, $statement->filters[0]->rule());
    }

    public function testRejectsATableRule(): void
    {
        $this->expectException(InvalidStructure::class);
        new DatabaseFilter(FilterRule::DoTable, []);
    }
}
