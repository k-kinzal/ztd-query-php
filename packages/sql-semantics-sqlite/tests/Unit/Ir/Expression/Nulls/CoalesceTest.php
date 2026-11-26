<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Expression\Nulls;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce;
use SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\CoalesceArgument;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\NullLiteral;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Expression\Nulls\Coalesce
 */
final class CoalesceTest extends TestCase
{
    public function testRetainsTheOriginalArgumentsThroughCallerArrayChanges(): void
    {
        $first = new CoalesceArgument(new NullLiteral());
        $other = new CoalesceArgument(new IntegerLiteral('17'));
        $entry = $first;
        $arguments = [&$entry, $other];

        $operation = new Coalesce($arguments);
        $entry = $other;

        self::assertSame([$first, $other], $operation->arguments);
    }
}
