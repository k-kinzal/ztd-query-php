<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\AlterSystemSetting::class)]
#[Medium]
final class AlterSystemTest extends TestCase
{
    public function testDeriveStatementDerivesTheSetting(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SYSTEM SET a = \'x\', 1')->facts->diagnostics);
    }

    public function testRenderWritesTheSettingAfterTheCommand(): void
    {
        self::assertSame(['ALTER SYSTEM SET a TO DEFAULT', 'ALTER SYSTEM RESET ALL', 'ALTER SYSTEM RESET a'], [(new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SYSTEM SET a = DEFAULT')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SYSTEM RESET ALL')->toString(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER SYSTEM RESET a')->toString()]);
    }
}
