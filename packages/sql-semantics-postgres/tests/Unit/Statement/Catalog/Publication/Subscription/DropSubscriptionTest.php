<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\DropSubscription::class)]
#[Medium]
final class DropSubscriptionTest extends TestCase
{
    public function testRenderWritesTheBehavior(): void
    {
        self::assertSame('DROP SUBSCRIPTION IF EXISTS s CASCADE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP SUBSCRIPTION IF EXISTS s CASCADE')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP SUBSCRIPTION s')->facts->diagnostics);
    }
}
