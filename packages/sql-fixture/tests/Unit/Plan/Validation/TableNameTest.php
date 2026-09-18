<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\FixturePlan;
use SqlFixture\Plan\Validation\TableName as Subject;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationCursor::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\DuplicateColumnBindingException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\CyclicDependencyException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Exception\UnboundedSelfReferenceException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Choice\ChoiceValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Choice\PlanContents::class)]
final class TableNameTest extends TestCase
{
    public function testAssertTableNameAcceptsUnderscoresAndDollarSigns(): void
    {
        (new Subject())->assertTableName('order_items');
        (new Subject())->assertTableName('a$b');
        self::assertSame('order_items', FixturePlan::table('order_items')->subjectTable());
        self::assertSame('a$b', FixturePlan::table('a$b')->subjectTable());
    }
}
