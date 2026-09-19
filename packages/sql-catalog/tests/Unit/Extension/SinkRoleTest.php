<?php

declare(strict_types=1);

namespace Tests\Unit\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Extension\SinkRole;

#[CoversClass(SinkRole::class)]
final class SinkRoleTest extends TestCase
{
    public function testCarriesSqlOnlyForTheCallsThatTakeAStatement(): void
    {
        self::assertTrue(SinkRole::Query->carriesSql());
        self::assertTrue(SinkRole::Prepare->carriesSql());
        self::assertFalse(SinkRole::Execute->carriesSql());
        self::assertFalse(SinkRole::Bind->carriesSql());
    }
}
