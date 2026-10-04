<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Database\DefaultSetting::class)]
#[Medium]
final class DefaultSettingTest extends TestCase
{
    public function testRenderWritesDefault(): void
    {
        self::assertSame('CREATE DATABASE d WITH TEMPLATE = DEFAULT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d TEMPLATE DEFAULT')->toString());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DATABASE d TEMPLATE = DEFAULT')->facts->diagnostics);
    }
}
