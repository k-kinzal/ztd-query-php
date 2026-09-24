<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Replication;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Replication\GtidUntil;

#[CoversClass(GtidUntil::class)]
#[Small]
final class GtidUntilTest extends TestCase
{
    public function testCasesSpellTheBoundaryKeywords(): void
    {
        self::assertSame(['SQL_BEFORE_GTIDS', 'SQL_AFTER_GTIDS'], array_column(GtidUntil::cases(), 'value'));
    }
}
