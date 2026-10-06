<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter::class)]
#[Small]
final class SpecialParameterTest extends TestCase
{
    public function testParameterOfEachKeywordForm(): void
    {
        self::assertSame(['timezone', 'transaction_isolation', 'session_authorization', null], [\SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter::TimeZone->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter::TransactionIsolationLevel->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter::SessionAuthorization->parameter(), \SqlSemantics\Platform\PostgreSql\Statement\Utility\Session\SpecialParameter::All->parameter()]);
    }
}
