<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Problem\ReplicationError;

#[CoversClass(ReplicationError::class)]
#[Small]
final class ReplicationErrorTest extends TestCase
{
    public function testCasesNameTheServerErrors(): void
    {
        self::assertCount(13, ReplicationError::cases());
        self::assertStringContainsString('ER_BAD_REPLICA_UNTIL_COND', ReplicationError::UntilCondition->value);
    }
}
