<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembership;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\FoldedMembershipLowering;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Semantics;

#[CoversClass(FoldedMembershipLowering::class)]
#[Small]
final class FoldedMembershipLoweringTest extends TestCase
{
    public function testLowerTracksMarkersButNeverBindsTheDiscardedName(): void
    {
        $value = (new Semantics())->analyze("SELECT (no_column + '?7' + ?02 + ?2) IN () AS answer");
        self::assertInstanceOf(Select::class, $value);
        $expression = $value->columns[0]->expression;

        self::assertInstanceOf(FoldedMembership::class, $expression);
        self::assertFalse($expression->matches);
        self::assertCount(2, $expression->bindings);
        self::assertSame('02', $expression->bindings[0]->label);
        self::assertSame('2', $expression->bindings[1]->label);
    }
}
