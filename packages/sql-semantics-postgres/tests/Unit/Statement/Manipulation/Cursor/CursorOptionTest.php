<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Manipulation\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\CursorOption::class)]
#[Small]
final class CursorOptionTest extends TestCase
{
    public function testValueIsTheKeywordSequence(): void
    {
        self::assertSame('NO SCROLL', \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Cursor\CursorOption::NoScroll->value);
    }
}
