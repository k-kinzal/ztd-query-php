<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Publication\Subscription;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Publication\Subscription\SubscriptionOptionAction::class)]
#[Medium]
final class SubscriptionOptionActionTest extends TestCase
{
    public function testSkipIsSpelledSkip(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s SKIP (lsn = \'0/14C0378\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s SKIP (lsn = \'0/14C0378\')')->toString());
    }
}
