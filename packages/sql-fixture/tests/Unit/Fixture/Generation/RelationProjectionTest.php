<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\Generation\RelationProjection as Subject;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Relation;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Fixture\PlanSchemaException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\ColumnDefinition::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\TableSchema::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class RelationProjectionTest extends TestCase
{
    public function testReferencedColumnsDeduplicatesParentKeys(): void
    {
        $plan = FixturePlan::from('a.id < b.a_id; a.id < c.a_id');
        self::assertSame(['id'], (new Subject())->referencedColumns($plan, 'a'));
        self::assertSame([], (new Subject())->referencedColumns($plan, 'b'));
    }

    public function testIsAlreadyLinkedRequiresEveryCompositeColumn(): void
    {
        $relation = Relation::oneToMany('a.(id, tenant)', 'b.(a_id, tenant)');
        $projection = new Subject();
        self::assertTrue($projection->isAlreadyLinked($relation, ['a_id' => 9, 'tenant' => 2]));
        self::assertFalse($projection->isAlreadyLinked($relation, ['a_id' => 9]));
    }

    public function testProjectMapsCompositeKeysWithoutUnrelatedColumns(): void
    {
        $relation = Relation::oneToMany('a.(id, tenant)', 'b.(a_id, tenant)');
        self::assertSame(['a_id' => 9, 'tenant' => 2], (new Subject())->project(['id' => 9, 'tenant' => 2, 'name' => 'A'], $relation));
    }
}
