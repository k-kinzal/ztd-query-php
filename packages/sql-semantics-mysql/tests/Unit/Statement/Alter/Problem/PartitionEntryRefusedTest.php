<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Alter\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\PartitionEntryRefused;

#[CoversClass(PartitionEntryRefused::class)]
#[Small]
final class PartitionEntryRefusedTest extends TestCase
{
    public function testMessageDescribesTheProblem(): void
    {
        self::assertSame('A partitioning clause is not a statement a client can send.', (new PartitionEntryRefused())->message());
    }
}
