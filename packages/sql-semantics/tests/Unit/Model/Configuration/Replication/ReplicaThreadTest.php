<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Replication\ReplicaThread;

#[CoversClass(ReplicaThread::class)]
#[Small]
final class ReplicaThreadTest extends TestCase
{
    public function testCasesSpellTheThreadKeywords(): void
    {
        self::assertSame(['IO_THREAD', 'SQL_THREAD'], array_column(ReplicaThread::cases(), 'value'));
    }
}
