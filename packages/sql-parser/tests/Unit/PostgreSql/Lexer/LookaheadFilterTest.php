<?php

declare(strict_types=1);

namespace Tests\Unit\PostgreSql\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Lexeme;
use SqlParser\Lexer\LexicalException;
use SqlParser\PostgreSql\Lexer\LookaheadFilter;

#[CoversClass(LookaheadFilter::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlParser\Lexer\SourcePosition::class)]
#[UsesClass(\SqlParser\Lexer\SourceException::class)]
#[Small]
#[UsesClass(\SqlParser\Parser\AlternativeParser::class)]
#[UsesClass(\SqlParser\Parser\ParseBranch::class)]
#[UsesClass(\SqlParser\Table\AlternativeCodec::class)]
final class LookaheadFilterTest extends TestCase
{
    public function testApplyRenamesByTheFollowingToken(): void
    {
        $lexemes = [new Lexeme('NOT', 'NOT', 0), new Lexeme('LIKE', 'LIKE', 4), new Lexeme('NOT', 'NOT', 9), new Lexeme('NULL_P', 'NULL', 13), new Lexeme('WITH', 'WITH', 18), new Lexeme('TIME', 'TIME', 23), new Lexeme('NULLS_P', 'NULLS', 28), new Lexeme('FIRST_P', 'FIRST', 34), new Lexeme('WITHOUT', 'WITHOUT', 40), new Lexeme('TIME', 'TIME', 48), new Lexeme('FORMAT', 'FORMAT', 53), new Lexeme('JSON', 'JSON', 60)];
        $names = array_map(static fn (Lexeme $lexeme): string => $lexeme->name, (new LookaheadFilter())->apply($lexemes, ''));

        self::assertSame(['NOT_LA', 'LIKE', 'NOT', 'NULL_P', 'WITH_LA', 'TIME', 'NULLS_LA', 'FIRST_P', 'WITHOUT_LA', 'TIME', 'FORMAT_LA', 'JSON'], $names);
    }

    public function testApplyFinishesUnicodeLiterals(): void
    {
        $source = 'U&"x" UESCAPE \'!\' U&\'y\' 1';
        $lexemes = [new Lexeme('UIDENT', 'U&"x"', 0), new Lexeme('UESCAPE', 'UESCAPE', 6), new Lexeme('SCONST', "'!'", 14), new Lexeme('USCONST', "U&'y'", 18), new Lexeme('ICONST', '1', 24)];
        $filtered = (new LookaheadFilter())->apply($lexemes, $source);

        self::assertSame(['IDENT', 'SCONST', 'ICONST'], array_map(static fn (Lexeme $lexeme): string => $lexeme->name, $filtered));
        self::assertSame('U&"x" UESCAPE \'!\'', $filtered[0]->text);
    }

    public function testUnicode(): void
    {
        $index = 0;
        $lexeme = (new LookaheadFilter())->unicode(new Lexeme('USCONST', "U&'y'", 0), [new Lexeme('USCONST', "U&'y'", 0)], $index, "U&'y'");

        self::assertSame('SCONST', $lexeme->name);
        self::assertSame(0, $index);
    }

    public function testUnicodeRejectsABadEscapeClause(): void
    {
        $index = 0;
        $lexemes = [new Lexeme('UIDENT', 'U&"x"', 0), new Lexeme('UESCAPE', 'UESCAPE', 6), new Lexeme('SCONST', "'ab'", 14)];

        $this->expectException(LexicalException::class);

        (new LookaheadFilter())->unicode($lexemes[0], $lexemes, $index, 'U&"x" UESCAPE \'ab\'');
    }
}
