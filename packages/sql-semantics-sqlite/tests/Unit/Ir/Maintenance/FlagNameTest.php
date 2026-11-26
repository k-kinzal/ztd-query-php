<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Maintenance;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName;

#[CoversClass(FlagName::class)]
#[Small]
final class FlagNameTest extends TestCase
{
    public function testNativeValues(): void
    {
        self::assertSame(FlagName::ForeignKeys, FlagName::from('foreign_keys'));
    }
}
