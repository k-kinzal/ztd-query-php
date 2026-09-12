<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Printing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Plan\Printing\RelationGroups as Subject;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\RelationKind;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class RelationGroupsTest extends TestCase
{
    public function testGroupCombinesOnlyMatchingLeftEndpoints(): void
    {
        $relations = [Relation::oneToMany('a.id', 'b.a_id'), Relation::oneToMany('a.id', 'c.a_id'), Relation::manyToOne('d.b_id', 'b.id')];
        $groups = (new Subject())->group($relations);
        self::assertSame([2, 1], array_map(count(...), array_values($groups)));
    }

    public function testGroupKeyIncludesOptionality(): void
    {
        self::assertSame('a.id <?', (new Subject())->groupKey(Relation::oneToMany('a.id', 'b.a_id', true)));
    }

    public function testOperatorPreservesBothMarkers(): void
    {
        $relation = new Relation(new ColumnRef('a', ['id']), RelationKind::OneToOne, new ColumnRef('b', ['a_id']), true, true);
        self::assertSame('?-?', (new Subject())->operator($relation));
    }
}
