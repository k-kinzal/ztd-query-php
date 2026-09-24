<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Account\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Definition\Account\Policy\ConnectionSecurity;

#[CoversClass(ConnectionSecurity::class)]
#[Medium]
final class ConnectionSecurityTest extends TestCase
{
    public function testCasesAreSpelledAsTheirSqlKeywords(): void
    {
        self::assertSame(['NONE', 'SSL', 'X509'], array_column(ConnectionSecurity::cases(), 'value'));
    }
}
