<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Literal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Literal\Utf32Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\Radix;
use SqlSemantics\Platform\MySql\Statement\Literal\RadixLiteral;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Utf32Radix::class)]
#[Small]
final class Utf32RadixTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, GrammarRelease, ?string}>
     */
    public static function providerValues(): iterable
    {
        yield 'unintroduced binary' => ['FFFFFFFF', 'binary', GrammarRelease::MySql847, null];
        yield 'empty' => ['', 'utf32', GrammarRelease::MySql847, null];
        yield 'leading padding' => ['61', 'utf32', GrammarRelease::MySql847, null];
        yield 'largest code point' => ['0010FFFF', 'utf32', GrammarRelease::MySql847, null];
        yield 'too large' => ['00110000', 'utf32', GrammarRelease::MySql847, '001100'];
        yield 'invalid after valid' => ['0000006100616263', 'utf32', GrammarRelease::MySql847, '006162'];
        yield 'modern surrogate' => ['0000D800', 'utf32', GrammarRelease::MySql847, '0000D8'];
        yield 'legacy surrogate' => ['0000D800', 'utf32', GrammarRelease::MySql5744, null];
    }

    #[DataProvider('providerValues')]
    public function testProblemChecksThePaddedCodePointDomain(string $digits, string $charset, GrammarRelease $release, ?string $invalid): void
    {
        $problem = (new Utf32Radix())->problem(new RadixLiteral(Radix::Hexadecimal, $digits, new Name($charset)), $release);

        self::assertSame($invalid, $problem === null ? null : strtoupper(bin2hex($problem->bytes)));
    }
}
