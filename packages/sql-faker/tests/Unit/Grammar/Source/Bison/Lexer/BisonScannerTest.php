<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Source\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\Bison\Lexer\ActionScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\BisonScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\DirectiveScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\IdentifierScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\NumberScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\PunctuationScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\Grammar\Source\Bison\Lexer\TypeTagScanner;
use SqlFaker\Grammar\Source\SourceCursor;

#[CoversNothing]
final class BisonScannerTest extends TestCase
{
    #[DataProvider('providerScanner')]
    public function testScanConsumesTheLexemeTheScannerClaims(BisonScanner $scanner, string $source): void
    {
        $cursor = new SourceCursor($source);

        self::assertTrue($scanner->handles($cursor->current()));

        $scanner->scan($cursor);

        self::assertTrue($cursor->atEnd(), 'the scanner left input behind');
    }

    #[DataProvider('providerScanner')]
    public function testHandlesRejectsWhatNoScannerReads(BisonScanner $scanner, string $source): void
    {
        unset($source);

        self::assertFalse($scanner->handles('@'));
    }

    /**
     * @return iterable<string, array{BisonScanner, string}>
     */
    public static function providerScanner(): iterable
    {
        yield 'directive' => [new DirectiveScanner(), '%token'];
        yield 'action' => [new ActionScanner(), '{ x; }'];
        yield 'type tag' => [new TypeTagScanner(), '<num>'];
        yield 'quoted literal' => [new QuotedLiteralScanner(), '"alias"'];
        yield 'number' => [new NumberScanner(), '42'];
        yield 'identifier' => [new IdentifierScanner(), 'expr'];
        yield 'punctuation' => [new PunctuationScanner(), ':'];
    }
}
