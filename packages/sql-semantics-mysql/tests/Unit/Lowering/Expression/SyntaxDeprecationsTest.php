<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Expression;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Lowering\Expression\SyntaxDeprecations;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;

#[CoversClass(SyntaxDeprecations::class)]
#[Small]
final class SyntaxDeprecationsTest extends TestCase
{
    public function testCompletedKeepsTheReleaseAndOriginalTextLiteralReduction(): void
    {
        $rules = new SyntaxDeprecations();
        $first = new Node('text_literal', 0, [new Token(0, 'NCHAR_STRING', "N'a'", 0)]);
        $continuation = new Node('text_literal', 0, [$first, new Token(0, 'TEXT_STRING', "'b'", 5)]);

        self::assertSame(Deprecated::National, $rules->completed($first, GrammarRelease::MySql847));
        self::assertNull($rules->completed($first, GrammarRelease::MySql5744));
        self::assertNull($rules->completed($continuation, GrammarRelease::MySql847));
    }
}
