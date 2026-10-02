<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\Rendering as R;

#[CoversClass(R\SqliteKeywordSpacing::class)]
#[Small]
final class SqliteKeywordSpacingTest extends TestCase
{
    #[DataProvider('providerBoundaries')]
    public function testJoinSeparatesActualKeywordBoundaries(string $left, string $gap, string $right, string $expected): void
    {
        self::assertSame($expected, R\SqliteKeywordSpacing::join($left, $gap, $right));
    }

    /**
     * @return list<array{string, string, string, string}>
     */
    public static function providerBoundaries(): array
    {
        return [
            ['CASE', '', '1', 'CASE 1'],
            ['WHEN', '', "'x'", "WHEN'x'"],
            ['1', '', 'THEN', '1 THEN'],
            ['1', '/*gap*/', 'THEN', '1/*gap*/THEN'],
            ['THEN', '', 'X\'00\'', 'THEN X\'00\''],
            ['ELSE', '', '名前', 'ELSE 名前'],
        ];
    }
}
