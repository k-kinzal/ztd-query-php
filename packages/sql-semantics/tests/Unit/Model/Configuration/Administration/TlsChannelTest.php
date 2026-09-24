<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Model\Configuration\Administration\TlsChannel;

#[CoversClass(TlsChannel::class)]
#[Small]
final class TlsChannelTest extends TestCase
{
    public function testCasesNameTheServerTlsContexts(): void
    {
        self::assertSame(['mysql_main', 'mysql_admin'], array_column(TlsChannel::cases(), 'value'));
    }
}
