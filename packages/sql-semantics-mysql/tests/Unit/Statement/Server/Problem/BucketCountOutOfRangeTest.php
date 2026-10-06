<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Server\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\BucketCountOutOfRange;

#[CoversClass(BucketCountOutOfRange::class)]
#[Small]
final class BucketCountOutOfRangeTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('Number of buckets 2000 is out of range in ANALYZE TABLE: it is 1 to 1024.', (new BucketCountOutOfRange(new Numeral('2000')))->message());
    }
}
