<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Schema\Reference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\InitialMode;

#[CoversClass(InitialMode::class)]
#[Small]
final class InitialModeTest extends TestCase
{
    public function testCasesSpellTheKeywordOfEachMode(): void
    {
        self::assertSame(['DEFERRED', 'IMMEDIATE'], array_column(InitialMode::cases(), 'value'));
    }
}
