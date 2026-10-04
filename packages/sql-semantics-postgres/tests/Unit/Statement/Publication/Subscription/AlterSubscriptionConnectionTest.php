<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription\AlterSubscriptionConnection::class)]
#[Medium]
final class AlterSubscriptionConnectionTest extends TestCase
{
    public function testRenderWritesTheConnection(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s CONNECTION \'host=b\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s CONNECTION \'host=b\'')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s CONNECTION \'host=b\'')->facts->diagnostics);
    }
}
