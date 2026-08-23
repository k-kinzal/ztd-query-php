<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\InsertRowProjection;
use ZtdQuery\Rewrite\InsertRowProjectionPlanner;

#[CoversClass(InsertRowProjectionPlanner::class)]
#[UsesClass(InsertRowProjection::class)]
final class InsertRowProjectionPlannerTest extends TestCase
{
    public function testPlansProvidedGeneratedDefaultsAndNulls(): void
    {
        $projections = (new InsertRowProjectionPlanner())->plan(
            ['id', 'name', 'status', 'note'],
            ['name' => "'Ada'"],
            ['id' => '99', 'status' => "'active'"],
            ['id' => 8],
        );

        self::assertCount(4, $projections);
        self::assertSame(8, $projections[0]->generatedIdentityValue());
        self::assertNull($projections[0]->defaultExpressionValue());
        self::assertSame("'Ada'", $projections[1]->providedExpression());
        self::assertSame("'active'", $projections[2]->defaultExpressionValue());
        self::assertTrue($projections[3]->isNullValue());
    }

    public function testUsesProvidedColumnsWhenTableShapeIsUnavailable(): void
    {
        $projections = (new InsertRowProjectionPlanner())->plan([], ['id' => '1', 'name' => "'Ada'"], []);

        self::assertCount(2, $projections);
        self::assertSame('id', $projections[0]->targetColumn());
        self::assertSame('1', $projections[0]->providedExpression());
        self::assertSame('name', $projections[1]->targetColumn());
    }
}
