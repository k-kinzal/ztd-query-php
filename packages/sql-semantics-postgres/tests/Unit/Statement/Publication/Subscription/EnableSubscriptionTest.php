<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription\EnableSubscription::class)]
#[Medium]
final class EnableSubscriptionTest extends TestCase
{
    public function testRenderWritesEnable(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s ENABLE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s ENABLE')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s DISABLE')->facts->diagnostics);
    }
}
