<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;

#[CoversClass(Lexeme::class)]
#[UsesClass(TerminalOccurrence::class)]
final class LexemeTest extends TestCase
{
    public function testRetainsCompoundAndOccurrenceProvenance(): void
    {
        $origin = new TerminalOccurrence('WITH_ROLLUP', 7);
        $lexeme = new Lexeme('ROLLUP', 'keyword', $origin, 'sql_lex.cc:with', 'with-rollup');
        self::assertSame($origin, $lexeme->origin);
        self::assertSame('with-rollup', $lexeme->phrase);
        self::assertSame('ROLLUP', $lexeme->text);
    }
}
