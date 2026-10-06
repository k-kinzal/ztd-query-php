<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Replication\Source\PrimaryKeyCheck;

#[CoversClass(PrimaryKeyCheck::class)]
#[Small]
final class PrimaryKeyCheckTest extends TestCase
{
    public function testCasesSpellEverySetting(): void
    {
        self::assertSame(['STREAM', 'ON', 'OFF', 'GENERATE'], array_column(PrimaryKeyCheck::cases(), 'value'));
    }
}
