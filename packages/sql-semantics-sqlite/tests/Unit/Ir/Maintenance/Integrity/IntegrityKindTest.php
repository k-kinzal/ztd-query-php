<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance\Integrity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\Integrity\IntegrityKind;

#[CoversClass(IntegrityKind::class)]
#[Small]
final class IntegrityKindTest extends TestCase
{
    public function testCasesFixTheTwoNativeModes(): void
    {
        self::assertSame(['integrity_check', 'quick_check'], array_column(IntegrityKind::cases(), 'value'));
    }
}
