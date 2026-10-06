<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetBinaryLogs;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetQueryCache;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetReplica;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetTarget;

#[CoversNothing]
#[Small]
final class ResetTargetTest extends TestCase
{
    public function testDeriveTargetIsDeclaredByEveryItem(): void
    {
        self::assertContains(ResetTarget::class, class_implements(ResetReplica::class));
        self::assertContains(ResetTarget::class, class_implements(ResetBinaryLogs::class));
        self::assertContains(ResetTarget::class, class_implements(ResetQueryCache::class));
    }
}
