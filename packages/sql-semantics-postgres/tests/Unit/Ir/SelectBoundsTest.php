<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\IntegerCaseArm;
use SqlSemantics\Platform\PostgreSql\Ir\From\OccurrenceScope;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\SelectBounds;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(SelectBounds::class)]
#[Small]
final class SelectBoundsTest extends TestCase
{
    public function testCheckAcceptsTheLastAvailableSlot(): void
    {
        $name = new Identifier('id');
        (new SelectBounds())->check(
            [new ColumnReference(0)],
            new TableSource(new Identifier('main'), new Identifier('t'), [$name]),
        );

        $this->addToAssertionCount(1);
    }

    public function testCaseArmsReturnsTheCheckedBranchesAtTheirOriginalDepth(): void
    {
        $value = new IntegerLiteral('2');
        $otherwise = new IntegerLiteral('8');
        $case = new IntegerCase([new IntegerCaseArm(new BooleanLiteral(true), $value)], $otherwise);
        $checked = (new SelectBounds())->caseArms($case, new OccurrenceScope(), [], 2, 5);

        self::assertSame([6, [[$value, 2], [$otherwise, 2]]], $checked);
    }
}
