<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;

#[CoversClass(OutputPart::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(TerminalOccurrence::class)]
final class OutputPartTest extends TestCase
{
    public function testKeepsTheResolvedSeparatorAndItsRuleSources(): void
    {
        $lexeme = new Lexeme('NOW', 'function', new TerminalOccurrence('NOW_SYM', 1), 'lex.h');
        $part = new OutputPart($lexeme, '', 'now:NOW', ['sql_lex.cc:function'], 1);
        self::assertSame('', $part->separator);
        self::assertSame(1, $part->allowed);
        self::assertSame(['sql_lex.cc:function'], $part->spacingRules);
        self::assertSame($lexeme, $part->lexeme);
    }
}
