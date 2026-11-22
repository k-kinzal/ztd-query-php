<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\EnableSubscription::class)]
#[Medium]
final class EnableSubscriptionTest extends TestCase
{
    public function testRenderWritesEnable(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s ENABLE', (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ALTER SUBSCRIPTION s ENABLE')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Testing\PostgreSql\Inspection())->inspect('ALTER SUBSCRIPTION s DISABLE')->facts->diagnostics);
    }
}
