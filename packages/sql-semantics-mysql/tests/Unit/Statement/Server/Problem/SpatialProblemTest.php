<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;

#[CoversClass(SpatialProblem::class)]
#[Small]
final class SpatialProblemTest extends TestCase
{
    public function testMessageDescribesEachRule(): void
    {
        self::assertSame('SRID 0 cannot be modified (ER_CANT_MODIFY_SRID_0).', (new SpatialProblem(SpatialRule::IdentifierZero))->message());
        self::assertSame('Attribute NAME is too long (ER_SRS_ATTRIBUTE_STRING_TOO_LONG).', (new SpatialProblem(SpatialRule::TooLong, SpatialAttributeKind::Name))->message());
    }
}
