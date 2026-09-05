<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GrammarParseException;
use SqlFaker\Grammar\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\BisonToken;
use SqlFaker\MySql\Bison\Lexer\BisonTokenType;
use SqlFaker\MySql\Bison\Lexer\DirectiveScanner;

#[CoversClass(DirectiveScanner::class)]
#[UsesClass(BisonTokenType::class)]
#[UsesClass(BisonToken::class)]
#[UsesClass(GrammarParseException::class)]
#[UsesClass(SourceCursor::class)]
final class DirectiveScannerTest extends TestCase
{
    public function testHandlesClaimsOnlyAPercentSign(): void
    {
        $scanner = new DirectiveScanner();

        self::assertTrue($scanner->handles('%'));
        self::assertFalse($scanner->handles('{'));
    }

    public function testScanSectionSeparatorReadsTwoPercentSigns(): void
    {
        $cursor = new SourceCursor('%% rules');

        $token = (new DirectiveScanner())->scan($cursor);

        self::assertSame(BisonTokenType::PercentPercent, $token->type);
        self::assertSame('%%', $token->value);
        self::assertSame(0, $token->offset);
        self::assertSame(' rules', $cursor->takeRest());
    }

    public function testScanPrologueReadsTheCodeBetweenItsDelimiters(): void
    {
        $cursor = new SourceCursor('%{ int x; %} rest');

        $token = (new DirectiveScanner())->scan($cursor);

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame(' int x; ', $token->value);
        self::assertSame(' rest', $cursor->takeRest());
    }

    public function testScanPrologueRunsAnUnterminatedBodyToTheEndOfTheFile(): void
    {
        $cursor = new SourceCursor('%{ int x;');

        $token = (new DirectiveScanner())->scan($cursor);

        self::assertSame(BisonTokenType::Prologue, $token->type);
        self::assertSame(' int x;', $token->value);
        self::assertTrue($cursor->atEnd());
    }

    public function testScanDirectiveNameKeepsTheLeadingPercentSign(): void
    {
        $cursor = new SourceCursor('%parse-param { x }');

        $token = (new DirectiveScanner())->scan($cursor);

        self::assertSame(BisonTokenType::Directive, $token->type);
        self::assertSame('%parse-param', $token->value);
        self::assertSame(' { x }', $cursor->takeRest());
    }

    public function testScanDirectiveNameReportsAPercentSignWithoutAName(): void
    {
        $cursor = new SourceCursor('% ');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected '%' at offset 0");

        (new DirectiveScanner())->scan($cursor);
    }

    public function testScanDispatchesOnTheCharacterAfterThePercentSign(): void
    {
        $scanner = new DirectiveScanner();

        self::assertSame(BisonTokenType::PercentPercent, $scanner->scan(new SourceCursor('%%'))->type);
        self::assertSame(BisonTokenType::Prologue, $scanner->scan(new SourceCursor('%{ x %}'))->type);
        self::assertSame(BisonTokenType::Directive, $scanner->scan(new SourceCursor('%token'))->type);
    }

    public function testScanReportsTheOffsetTheDirectiveStartedAt(): void
    {
        $cursor = new SourceCursor('ab%token');
        $cursor->advance(2);

        self::assertSame(2, (new DirectiveScanner())->scan($cursor)->offset);
    }
}
