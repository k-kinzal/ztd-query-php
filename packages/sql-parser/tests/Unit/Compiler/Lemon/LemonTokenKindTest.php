<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Lemon\LemonTokenKind;

#[CoversClass(LemonTokenKind::class)]
#[Small]
final class LemonTokenKindTest extends TestCase
{
    public function testCases(): void
    {
        self::assertCount(11, LemonTokenKind::cases());
        self::assertSame('Arrow', LemonTokenKind::Arrow->name);
    }
}
