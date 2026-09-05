<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Compiler\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Compiler\Bison\Lexer\ActionScanner;
use SqlFaker\Compiler\Bison\Lexer\BisonScanner;
use SqlFaker\Compiler\Bison\Lexer\BisonScannerChain;
use SqlFaker\Compiler\Bison\Lexer\DirectiveScanner;
use SqlFaker\Compiler\Bison\Lexer\IdentifierScanner;
use SqlFaker\Compiler\Bison\Lexer\NumberScanner;
use SqlFaker\Compiler\Bison\Lexer\PunctuationScanner;
use SqlFaker\Compiler\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Compiler\Bison\Lexer\TypeTagScanner;

#[CoversClass(BisonScannerChain::class)]
#[UsesClass(ActionScanner::class)]
#[UsesClass(DirectiveScanner::class)]
#[UsesClass(IdentifierScanner::class)]
#[UsesClass(NumberScanner::class)]
#[UsesClass(PunctuationScanner::class)]
#[UsesClass(QuotedLiteralScanner::class)]
#[UsesClass(TypeTagScanner::class)]
final class BisonScannerChainTest extends TestCase
{
    /**
     * @param class-string<BisonScanner> $expected
     */
    #[DataProvider('providerClaimedCharacter')]
    public function testScannerForReachesTheScannerThatOwnsEachOpeningCharacter(string $character, string $expected): void
    {
        self::assertInstanceOf($expected, BisonScannerChain::forBisonGrammar()->scannerFor($character));
    }

    /**
     * @return iterable<string, array{string, class-string<BisonScanner>}>
     */
    public static function providerClaimedCharacter(): iterable
    {
        yield 'directive' => ['%', DirectiveScanner::class];
        yield 'action' => ['{', ActionScanner::class];
        yield 'type tag' => ['<', TypeTagScanner::class];
        yield 'character literal' => ["'", QuotedLiteralScanner::class];
        yield 'string literal' => ['"', QuotedLiteralScanner::class];
        yield 'number' => ['7', NumberScanner::class];
        yield 'identifier' => ['x', IdentifierScanner::class];
        yield 'punctuation' => [':', PunctuationScanner::class];
    }

    public function testForBisonGrammarCoversEveryLexemeOfTheGrammarLanguage(): void
    {
        $chain = BisonScannerChain::forBisonGrammar();

        $unclaimed = array_values(array_filter(
            ['%', '{', '<', "'", '"', '7', 'x', ':', ';', '|', '=', ',', '(', ')', '[', ']'],
            static fn (string $character): bool => $chain->scannerFor($character) === null,
        ));

        self::assertSame([], $unclaimed);
    }

    public function testScannerForReportsNothingForAnUnclaimedCharacter(): void
    {
        self::assertNull(BisonScannerChain::forBisonGrammar()->scannerFor('@'));
    }

    public function testScannerForFindsNothingInAnEmptyChain(): void
    {
        self::assertNull((new BisonScannerChain([]))->scannerFor('x'));
    }

    public function testScannerForReturnsTheFirstScannerThatClaimsTheCharacter(): void
    {
        $chain = new BisonScannerChain([new IdentifierScanner(), new NumberScanner()]);

        self::assertInstanceOf(IdentifierScanner::class, $chain->scannerFor('x'));
        self::assertInstanceOf(NumberScanner::class, $chain->scannerFor('1'));
    }
}
