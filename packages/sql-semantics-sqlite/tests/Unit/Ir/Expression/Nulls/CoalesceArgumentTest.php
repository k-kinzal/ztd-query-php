<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Nulls;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument;
use SqlSemantics\Platform\Sqlite\Ir\Parameter;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument
 */
final class CoalesceArgumentTest extends TestCase
{
    public function testRetainsTheExactMarkerAndItsOperandRole(): void
    {
        $marker = new Parameter('001');

        self::assertSame($marker, (new CoalesceArgument($marker))->expression);
    }
}
