<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\UnknownPartition;

#[CoversClass(UnknownPartition::class)]
#[Small]
final class UnknownPartitionTest extends TestCase
{
    public function testMessageNamesThePartitionAndTheTable(): void
    {
        self::assertSame("Unknown partition 'p9' in table 't'", (new UnknownPartition('p9', 't'))->message());
    }
}
