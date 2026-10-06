<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\RefreshSubscription::class)]
#[Medium]
final class RefreshSubscriptionTest extends TestCase
{
    public function testRenderWritesTheOptions(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s REFRESH PUBLICATION WITH (copy_data = FALSE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s REFRESH PUBLICATION WITH (copy_data = false)')->toString());
    }

    public function testDeriveStatementDerivesTheOptions(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s REFRESH PUBLICATION')->facts->diagnostics);
    }
}
