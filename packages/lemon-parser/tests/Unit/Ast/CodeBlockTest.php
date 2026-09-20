<?php

declare(strict_types=1);

namespace Tests\Unit\Ast;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\Location;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CodeBlock::class)]
#[UsesClass(Location::class)]
#[Small]
final class CodeBlockTest extends TestCase
{
    public function testCode(): void
    {
        $block = new CodeBlock(' A = B; ', new Location(3, 30));

        self::assertSame(' A = B; ', $block->code);
        self::assertSame('3:30', (string) $block->location);
    }
}
