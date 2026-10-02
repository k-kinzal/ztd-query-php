<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Expression\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Statement\Expression\Rendering as R;
use SqlSemantics\Statement\Validation\Failure\InvalidConstruction;

#[CoversClass(R\SqliteElseLayout::class)]
#[Small]
final class SqliteElseLayoutTest extends TestCase
{
    public function testAppendKeepsTheActualFallbackAndItsCommentGap(): void
    {
        $layout = new R\SqliteElseLayout('else', '/*before*/', '/*after*/');
        self::assertSame('CASE WHEN 1 THEN 2/*before*/else/*after*/3', $layout->append('CASE WHEN 1 THEN 2', '3'));
    }

    public function testRejectsAnExtraKeywordInTheDelimiter(): void
    {
        $this->expectException(InvalidConstruction::class);
        new R\SqliteElseLayout('ELSE END');
    }

    public function testRejectsATruncatedBlockComment(): void
    {
        $this->expectException(InvalidConstruction::class);
        new R\SqliteElseLayout(after: '/* hidden');
    }
}
