<?php

declare(strict_types=1);

namespace Tests\Unit\Compiler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Compiler\GrammarSourceException;

#[CoversClass(GrammarSourceException::class)]
#[Small]
final class GrammarSourceExceptionTest extends TestCase
{
    public function testUnexpected(): void
    {
        self::assertSame("Expected a rule name but found ';' on line 4", GrammarSourceException::unexpected('a rule name', "';'", 4)->getMessage());
    }

    public function testUnterminated(): void
    {
        self::assertSame('Unterminated code block opened on line 2', GrammarSourceException::unterminated('code block', 2)->getMessage());
    }
}
