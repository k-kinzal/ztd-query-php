<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Publication\Subscription\AlterSubscriptionPublications::class)]
#[Medium]
final class AlterSubscriptionPublicationsTest extends TestCase
{
    public function testRenderWritesTheAction(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s SET PUBLICATION p3, p4 WITH (refresh = FALSE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s SET PUBLICATION p3, p4 WITH (refresh = false)')->toString());
    }

    public function testDeriveStatementDerivesTheOptions(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s DROP PUBLICATION p')->facts->diagnostics);
    }
}
