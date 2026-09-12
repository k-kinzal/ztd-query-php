<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Parsing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\Parsing\RelationCursor as Subject;
use SqlFixture\Plan\RelationKind;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\ColumnRef::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\FixturePlan::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanParser::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Relation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RelationKind::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\RelationSide::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\PlanStatements::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Parsing\RelationReader::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\PlanStructureException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\PlanTables::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\RelationGroups::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Printing\StatementPrinter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\PlanValidation::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Plan\Validation\TableName::class)]
final class RelationCursorTest extends TestCase
{
    public function testReadIdentifierConsumesQuotedNames(): void
    {
        $cursor = new Subject('`order items`.id');
        self::assertSame('order items', $cursor->readIdentifier('table'));
        self::assertSame('.', $cursor->peek());
    }

    public function testReadOperatorConsumesDirection(): void
    {
        $cursor = new Subject('>?');
        self::assertSame(RelationKind::ManyToOne, $cursor->readOperator());
        self::assertSame('?', $cursor->peek());
    }

    public function testReadOptionalMarkerLeavesUnmarkedInputAlone(): void
    {
        $cursor = new Subject('?a');
        self::assertTrue($cursor->readOptionalMarker());
        self::assertFalse($cursor->readOptionalMarker());
        self::assertSame('a', $cursor->peek());
    }

    public function testSkipWhitespaceStopsBeforeIdentifier(): void
    {
        $cursor = new Subject(' 	
        a');
        $cursor->skipWhitespace();
        self::assertSame('a', $cursor->peek());
    }

    public function testExpectEndAcceptsAnExhaustedStatement(): void
    {
        $cursor = new Subject('a');
        self::assertSame('a', $cursor->readIdentifier('table'));
        $cursor->expectEnd();
        self::assertNull($cursor->peek());
    }

    public function testPeekDoesNotAdvance(): void
    {
        $cursor = new Subject('ab');
        self::assertSame('a', $cursor->peek());
        self::assertSame(0, $cursor->offset);
    }
}
