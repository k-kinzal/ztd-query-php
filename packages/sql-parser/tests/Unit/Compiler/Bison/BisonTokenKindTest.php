<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Bison;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Bison\BisonTokenKind;

#[CoversClass(BisonTokenKind::class)]
#[Small]
final class BisonTokenKindTest extends TestCase
{
    public function testCases(): void
    {
        self::assertCount(14, BisonTokenKind::cases());
        self::assertSame('Section', BisonTokenKind::Section->name);
    }
}
