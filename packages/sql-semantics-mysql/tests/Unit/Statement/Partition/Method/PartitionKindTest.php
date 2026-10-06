<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;

#[CoversClass(PartitionKind::class)]
#[Small]
final class PartitionKindTest extends TestCase
{
    public function testCasesSpellRangeAndList(): void
    {
        self::assertSame(['RANGE', 'LIST'], array_column(PartitionKind::cases(), 'value'));
    }
}
