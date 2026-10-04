<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Replica;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\ReplicaThread;

#[CoversClass(ReplicaThread::class)]
#[Small]
final class ReplicaThreadTest extends TestCase
{
    public function testCasesSpellBothThreads(): void
    {
        self::assertSame(['SQL_THREAD', 'IO_THREAD'], array_column(ReplicaThread::cases(), 'value'));
    }
}
