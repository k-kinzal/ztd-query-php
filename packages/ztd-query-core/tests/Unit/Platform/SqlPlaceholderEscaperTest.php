<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlPlaceholderEscaper;

#[CoversNothing]
final class SqlPlaceholderEscaperTest extends TestCase
{
    public function testEscapeLeavesAStatementWithNoPlaceholderCharacterAlone(): void
    {
        self::assertSame('SELECT 1', (new FakeSqlPlaceholderEscaper())->escape('SELECT 1'));
    }

    public function testEscapeWritesAPlaceholderCharacterSoNoDriverReadsItAsOne(): void
    {
        self::assertSame(
            "SELECT '??' FROM t",
            (new FakeSqlPlaceholderEscaper())->escape("SELECT '?' FROM t"),
        );
    }
}
