<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription\AlterSubscriptionOptions::class)]
#[Medium]
final class AlterSubscriptionOptionsTest extends TestCase
{
    public function testRenderWritesSet(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s SET (slot_name = NONE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s SET (slot_name = NONE)')->toString());
    }

    public function testDeriveStatementDerivesTheParameters(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s SET (binary = true)')->facts->diagnostics);
    }
}
