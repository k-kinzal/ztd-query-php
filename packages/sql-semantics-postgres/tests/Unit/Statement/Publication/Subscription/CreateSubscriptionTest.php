<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription\CreateSubscription::class)]
#[Medium]
final class CreateSubscriptionTest extends TestCase
{
    public function testRenderWritesThePublications(): void
    {
        self::assertSame('CREATE SUBSCRIPTION s CONNECTION \'host=a\' PUBLICATION p1, p2 WITH (enabled = FALSE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SUBSCRIPTION s CONNECTION \'host=a\' PUBLICATION p1, p2 WITH (enabled = false)')->toString());
    }

    public function testDeriveStatementDerivesTheParameters(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SUBSCRIPTION s CONNECTION \'x\' PUBLICATION p')->facts->diagnostics);
    }
}
