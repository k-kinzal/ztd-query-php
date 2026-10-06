<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\ZoneKeyword::class)]
#[Medium]
final class ZoneKeywordTest extends TestCase
{
    public function testDefaultAndLocalAreKeywords(): void
    {
        self::assertSame(['SET TIME ZONE DEFAULT', 'SET TIME ZONE LOCAL'], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TIME ZONE DEFAULT')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SET TIME ZONE LOCAL')->toString()]);
    }
}
