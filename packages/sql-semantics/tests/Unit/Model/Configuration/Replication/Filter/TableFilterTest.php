<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Replication\Filter\FilterRule;
use SqlSemantics\Model\Configuration\Replication\Filter\TableFilter;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TableFilter::class)]
#[Medium]
final class TableFilterTest extends TestCase
{
    public function testRuleReturnsTheTableRule(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CHANGE REPLICATION FILTER REPLICATE_IGNORE_TABLE = (s.missing, `s`.`t`)');
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertEquals([new TableFilter(FilterRule::IgnoreTable, [new QualifiedName(['s', 'missing']), new QualifiedName(['s', 't'])])], $statement->filters);
        self::assertSame(FilterRule::IgnoreTable, $statement->filters[0]->rule());
    }

    public function testRejectsAnUnqualifiedTable(): void
    {
        $this->expectException(InvalidStructure::class);
        new TableFilter(FilterRule::DoTable, [new QualifiedName(['t'])]);
    }

    public function testRejectsADatabaseRule(): void
    {
        $this->expectException(InvalidStructure::class);
        new TableFilter(FilterRule::DoDatabase, []);
    }
}
