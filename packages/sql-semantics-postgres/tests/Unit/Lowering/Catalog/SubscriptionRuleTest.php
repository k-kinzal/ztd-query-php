<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Lowering\Catalog\SubscriptionRule::class)]
#[Medium]
final class SubscriptionRuleTest extends TestCase
{
    public function testStatementLowersEveryForm(): void
    {
        self::assertSame('ALTER SUBSCRIPTION s ADD PUBLICATION p WITH (refresh = FALSE)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SUBSCRIPTION s ADD PUBLICATION p WITH (refresh = false)')->toString());
    }
}
