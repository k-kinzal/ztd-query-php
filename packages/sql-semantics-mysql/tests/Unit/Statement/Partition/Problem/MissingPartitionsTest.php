<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;
use SqlSemantics\Platform\MySql\Statement\Partition\Problem\MissingPartitions;

#[CoversClass(MissingPartitions::class)]
#[Small]
final class MissingPartitionsTest extends TestCase
{
    public function testMessageIdentifiesThePartitionMethod(): void
    {
        self::assertSame('For LIST partitions each partition must be defined.', (new MissingPartitions(PartitionKind::List))->message());
    }
}
