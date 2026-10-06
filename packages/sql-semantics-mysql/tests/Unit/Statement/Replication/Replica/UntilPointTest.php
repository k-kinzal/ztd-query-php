<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Replica;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Replica\UntilPoint;

#[CoversClass(UntilPoint::class)]
#[Small]
final class UntilPointTest extends TestCase
{
    public function testCasesSpellEveryCondition(): void
    {
        self::assertSame(['SQL_BEFORE_GTIDS', 'SQL_AFTER_GTIDS', 'SQL_AFTER_MTS_GAPS'], array_column(UntilPoint::cases(), 'value'));
    }
}
