<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Spacing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;

#[CoversClass(SpacingConstraint::class)]

final class SpacingConstraintTest extends TestCase
{
    public function testIntersectRetainsBothCausesOfAContradictionAndCannotBeOverridden(): void
    {
        $join = new SpacingConstraint(SpacingConstraint::JOIN, ['function']);
        $space = new SpacingConstraint(SpacingConstraint::SPACE, ['keyword-phrase']);
        $both = $join->intersect($space);
        self::assertSame(0, $both->allowed);
        self::assertSame(['function', 'keyword-phrase'], $both->rules);
        self::assertSame(0, $both->intersect(new SpacingConstraint())->allowed);
        self::assertSame(SpacingConstraint::JOIN, $join->allowed);
        self::assertSame(['function'], $join->intersect($join)->rules);
    }

    public function testSeparatorPrefersASpaceWheneverItIsAllowed(): void
    {
        self::assertSame(' ', (new SpacingConstraint())->separator());
        self::assertSame(' ', (new SpacingConstraint(SpacingConstraint::SPACE))->separator());
        self::assertSame('', (new SpacingConstraint(SpacingConstraint::JOIN))->separator());
        self::assertNull((new SpacingConstraint(0))->separator());
    }
}
