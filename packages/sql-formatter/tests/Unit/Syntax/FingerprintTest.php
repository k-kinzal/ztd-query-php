<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Syntax\Fingerprint;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

#[CoversClass(Fingerprint::class)]
final class FingerprintTest extends TestCase
{
    public function testOfIgnoresWhitespaceAndPositions(): void
    {
        $left = new Node('expr', 2, [new Token(1, 'NUM', '1', 4, '  ')]);
        $right = new Node('expr', 2, [new Token(1, 'NUM', '1', 12, "\n")]);
        self::assertSame(Fingerprint::of($left), Fingerprint::of($right));
    }

    public function testOfDetectsChangedGrammarAndLiteralContent(): void
    {
        $left = new Node('expr', 2, [new Token(1, 'NUM', '1', 0)]);
        self::assertNotSame(Fingerprint::of($left), Fingerprint::of(new Node('expr', 3, $left->children)));
        self::assertNotSame(Fingerprint::of($left), Fingerprint::of(new Node('expr', 2, [new Token(1, 'NUM', '2', 0)])));
    }
}
