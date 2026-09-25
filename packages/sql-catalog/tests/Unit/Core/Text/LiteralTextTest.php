<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Text;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Text\LiteralText;

#[CoversClass(LiteralText::class)]
final class LiteralTextTest extends TestCase
{
    public function testDisplayIsTheTextItself(): void
    {
        self::assertSame('SELECT 1', (new LiteralText('SELECT 1'))->display());
    }

    public function testTextIsKept(): void
    {
        self::assertSame(' ', (new LiteralText(' '))->text);
    }
}
