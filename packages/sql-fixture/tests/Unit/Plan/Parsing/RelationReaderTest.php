<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Parsing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\Parsing\RelationReader as Subject;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class RelationReaderTest extends TestCase
{
    public function testParseStatementExpandsGroupedTargets(): void
    {
        $reader = new Subject(new \SqlFixture\Plan\Parsing\RelationCursor('a.id < [b.a_id, c.a_id]'));
        $relations = $reader->parseStatement();
        self::assertIsArray($relations);
        self::assertSame(['b', 'c'], array_map(static fn (Relation $relation): string => $relation->child()->table, $relations));
    }

    public function testReadTargetsReadsACompositeEndpoint(): void
    {
        $targets = (new Subject(new \SqlFixture\Plan\Parsing\RelationCursor('[b.(a_id, tenant), c.id]')))->readTargets();
        self::assertSame(['a_id', 'tenant'], $targets[0]->columns);
        self::assertSame('c', $targets[1]->table);
    }

    public function testReadEndpointPreservesQuotedIdentifiers(): void
    {
        $endpoint = (new Subject(new \SqlFixture\Plan\Parsing\RelationCursor('`orders`.`customer_id`')))->readEndpoint();
        self::assertSame('orders', $endpoint->table);
        self::assertSame(['customer_id'], $endpoint->columns);
    }

    public function testBuildRelationsCarriesOptionalityToEachTarget(): void
    {
        $relations = (new Subject(new \SqlFixture\Plan\Parsing\RelationCursor('')))->buildRelations(new ColumnRef('a', ['id']), RelationKind::OneToMany, [new ColumnRef('b', ['a_id']), new ColumnRef('c', ['a_id'])], false, true);
        self::assertCount(2, $relations);
        self::assertFalse($relations[0]->parentIsOptional());
        self::assertTrue($relations[1]->childIsOptional());
    }
}
