<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\ActionScanner;
use SqlFaker\MySql\Bison\Lexer\BisonScanner;
use SqlFaker\MySql\Bison\Lexer\DirectiveScanner;
use SqlFaker\MySql\Bison\Lexer\IdentifierScanner;
use SqlFaker\MySql\Bison\Lexer\NumberScanner;
use SqlFaker\MySql\Bison\Lexer\PunctuationScanner;
use SqlFaker\MySql\Bison\Lexer\QuotedLiteralScanner;
use SqlFaker\MySql\Bison\Lexer\TypeTagScanner;

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
