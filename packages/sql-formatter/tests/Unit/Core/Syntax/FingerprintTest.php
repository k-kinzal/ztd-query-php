<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Syntax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFormatter\Core\Syntax\Fingerprint;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

#[CoversClass(Fingerprint::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Formatter::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Core\Compact\Settings::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\MySql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\PostgreSql\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Platform\Sqlite\Dialect::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFormatter\Facade\DialectFactory::class)]
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
