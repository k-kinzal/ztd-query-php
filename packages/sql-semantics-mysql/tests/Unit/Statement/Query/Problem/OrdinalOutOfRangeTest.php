<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;

#[CoversClass(OrdinalOutOfRange::class)]
#[Small]
final class OrdinalOutOfRangeTest extends TestCase
{
    public function testMessageNamesThePositionAndTheLength(): void
    {
        self::assertSame("Unknown column '4' in 'order clause': the select list has 2 items.", (new OrdinalOutOfRange(4, 2))->message());
    }
}
