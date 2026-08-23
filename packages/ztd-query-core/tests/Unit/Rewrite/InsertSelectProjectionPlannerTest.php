<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\InsertSelectProjection;
use ZtdQuery\Rewrite\InsertSelectProjectionPlanner;

#[CoversClass(InsertSelectProjectionPlanner::class)]
#[UsesClass(InsertSelectProjection::class)]
final class InsertSelectProjectionPlannerTest extends TestCase
{
    public function testPlansSourcePositionsGeneratedDefaultsAndNulls(): void
    {
        $projections = (new InsertSelectProjectionPlanner())->plan(
            ['id', 'name', 'status', 'note'],
            ['name'],
            ['status' => "'active'"],
            ['id' => 8],
        );

        self::assertCount(4, $projections);
        self::assertSame('id', $projections[0]->targetColumn());
        self::assertSame(8, $projections[0]->generatedIdentityStart());
        self::assertNull($projections[0]->defaultExpressionValue());
        self::assertSame('name', $projections[1]->targetColumn());
        self::assertSame(0, $projections[1]->sourceIndex());
        self::assertSame('status', $projections[2]->targetColumn());
        self::assertSame("'active'", $projections[2]->defaultExpressionValue());
        self::assertSame('note', $projections[3]->targetColumn());
        self::assertTrue($projections[3]->isNullValue());
    }

    public function testUsesInsertColumnsWhenTableShapeIsUnavailable(): void
    {
        $projections = (new InsertSelectProjectionPlanner())->plan([], ['id', 'name'], [], []);

        self::assertCount(2, $projections);
        self::assertSame(0, $projections[0]->sourceIndex());
        self::assertSame(1, $projections[1]->sourceIndex());
    }

    public function testGeneratedIdentityTakesPriorityOverDefault(): void
    {
        $projections = (new InsertSelectProjectionPlanner())->plan(
            ['id'],
            [],
            ['id' => 'DEFAULT_ID()'],
            ['id' => 7],
        );

        self::assertSame(7, $projections[0]->generatedIdentityStart());
        self::assertNull($projections[0]->defaultExpressionValue());
    }
}
