<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\Filter\FilterRule;
use SqlSemantics\Model\Configuration\Replication\Filter\WildTableFilter;
use SqlSemantics\Model\Statement\Server\Replication\ChangeReplicationFilterStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(WildTableFilter::class)]
#[Medium]
final class WildTableFilterTest extends TestCase
{
    public function testRuleReturnsTheWildcardRule(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_WILD_DO_TABLE = ('db%.t_', 'x.%')");
        self::assertInstanceOf(ChangeReplicationFilterStatement::class, $statement);
        self::assertInstanceOf(WildTableFilter::class, $statement->filters[0]);
        self::assertSame(FilterRule::WildDoTable, $statement->filters[0]->rule());
        self::assertSame(["'db%.t_'", "'x.%'"], array_map(static fn ($pattern): string => $pattern->text, $statement->filters[0]->patterns));
    }

    public function testDiagnosesAPatternWithoutADot(): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage(InputViolation::ReplicationFilter->message());
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CHANGE REPLICATION FILTER REPLICATE_WILD_IGNORE_TABLE = ('db%')");
    }

    public function testRejectsADatabaseRule(): void
    {
        $this->expectException(InvalidStructure::class);
        new WildTableFilter(FilterRule::IgnoreDatabase, []);
    }
}
