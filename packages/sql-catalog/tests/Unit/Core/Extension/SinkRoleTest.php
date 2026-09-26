<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Extension\SinkRole;

#[CoversClass(SinkRole::class)]
final class SinkRoleTest extends TestCase
{
    public function testCarriesSqlOnlyForTheCallsThatTakeAStatement(): void
    {
        self::assertTrue(SinkRole::Query->carriesSql());
        self::assertTrue(SinkRole::Compose->carriesSql());
        self::assertTrue(SinkRole::Prepare->carriesSql());
        self::assertFalse(SinkRole::Execute->carriesSql());
        self::assertFalse(SinkRole::Bind->carriesSql());
    }

    public function testReturnsSqlOnlyForTheCallThatHandsTheStatementBack(): void
    {
        self::assertTrue(SinkRole::Compose->returnsSql());
        self::assertFalse(SinkRole::Query->returnsSql());
        self::assertFalse(SinkRole::Prepare->returnsSql());
    }
}
