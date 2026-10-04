<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetWord;

#[CoversClass(SetWord::class)]
#[Small]
final class SetWordTest extends TestCase
{
    public function testCasesSpellTheKeywords(): void
    {
        self::assertSame(['DEFAULT', 'ON', 'ALL', 'BINARY', 'ROW', 'SYSTEM'], array_column(SetWord::cases(), 'value'));
    }
}
