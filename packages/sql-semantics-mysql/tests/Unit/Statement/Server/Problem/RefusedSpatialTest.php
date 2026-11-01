<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\RefusedSpatial;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialCommand;

#[CoversClass(RefusedSpatial::class)]
#[Small]
final class RefusedSpatialTest extends TestCase
{
    public function testMessageRetainsTheTypedOperationAndCause(): void
    {
        $cause = new SpatialProblem(SpatialRule::IdentifierZero);
        $problem = new RefusedSpatial(SpatialCommand::Drop, $cause);
        self::assertSame($cause, $problem->problem);
        self::assertSame(SpatialCommand::Drop, $problem->command);
        self::assertSame($cause->message(), $problem->message());
    }
}
