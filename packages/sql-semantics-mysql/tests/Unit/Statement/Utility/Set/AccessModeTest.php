<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode;

#[CoversClass(AccessMode::class)]
#[Small]
final class AccessModeTest extends TestCase
{
    public function testCasesSpellTheModes(): void
    {
        self::assertSame(['READ WRITE', 'READ ONLY'], array_column(AccessMode::cases(), 'value'));
    }
}
