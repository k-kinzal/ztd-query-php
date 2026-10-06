<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Characteristic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\AccessLevel;

#[CoversClass(AccessLevel::class)]
#[Small]
final class AccessLevelTest extends TestCase
{
    public function testCasesNameTheFourLevelsInManualOrder(): void
    {
        self::assertSame(['ContainsSql', 'NoSql', 'ReadsSqlData', 'ModifiesSqlData'], array_map(static fn (AccessLevel $level): string => $level->name, AccessLevel::cases()));
    }
}
