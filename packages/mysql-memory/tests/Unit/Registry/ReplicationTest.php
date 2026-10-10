<?php

declare(strict_types=1);

namespace Tests\Unit\Registry;

use MySqlMemory\Registry\Replication;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(Replication::class)]
#[Small]
final class ReplicationTest extends TestCase
{
    public function testResetClosesRepositoriesAndKeepsAClosedLog(): void
    {
        $replication = new Replication();
        $replication->initialized = true;
        $replication->missing = true;
        $replication->reset();

        self::assertSame([false, false, true, false], [$replication->initialized, $replication->applying, $replication->closed, $replication->missing]);
    }
}
