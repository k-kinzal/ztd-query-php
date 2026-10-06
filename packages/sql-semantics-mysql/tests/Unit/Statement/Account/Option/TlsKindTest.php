<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Option;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind;

#[CoversClass(TlsKind::class)]
#[Small]
final class TlsKindTest extends TestCase
{
    public function testCasesNameTheRequirements(): void
    {
        self::assertSame(['None', 'Ssl', 'X509', 'Specified'], array_column(TlsKind::cases(), 'name'));
    }
}
