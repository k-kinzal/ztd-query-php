<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\Relation;
use SqlFixture\Plan\Validation\PlanValidation as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class PlanValidationTest extends TestCase
{
    public function testRejectColumnsBoundTwiceAcceptsDistinctForeignKeys(): void
    {
        $relations = [Relation::oneToMany('a.id', 'b.a_id'), Relation::oneToMany('a.id', 'b.other_id')];
        (new Subject())->rejectColumnsBoundTwice($relations);
        self::assertSame(['a', 'b'], (new Subject())->sortByDependency(['b', 'a'], $relations));
    }

    public function testRejectUnboundedSelfReferencesAcceptsOptionalChildren(): void
    {
        $relations = [Relation::oneToMany('node.id', 'node.parent_id', true)];
        (new Subject())->rejectUnboundedSelfReferences($relations);
        self::assertSame(['node'], (new Subject())->sortByDependency(['node'], $relations));
    }

    public function testSortByDependencyPreservesIndependentOrder(): void
    {
        $relations = [Relation::oneToMany('a.id', 'b.a_id')];
        self::assertSame(['c', 'a', 'b'], (new Subject())->sortByDependency(['b', 'c', 'a'], $relations));
    }

    public function testWaitsForAnyChecksOnlyPendingParents(): void
    {
        $relations = [Relation::oneToMany('a.id', 'b.a_id')];
        self::assertTrue((new Subject())->waitsForAny('b', $relations, ['a']));
        self::assertFalse((new Subject())->waitsForAny('b', $relations, ['b']));
    }
}
