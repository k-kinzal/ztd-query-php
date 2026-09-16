<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\Lemon\LemonToken;
use SqlParser\Compiler\Lemon\LemonTokenKind;

#[CoversClass(LemonToken::class)]
#[Small]
final class LemonTokenTest extends TestCase
{
    public function testIs(): void
    {
        $token = new LemonToken(LemonTokenKind::Dot, '.', 2);

        self::assertTrue($token->is(LemonTokenKind::Dot));
        self::assertFalse($token->is(LemonTokenKind::Pipe));
        self::assertSame(2, $token->line);
    }
}
