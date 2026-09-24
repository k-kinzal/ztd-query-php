<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Utility;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Identifiers;
use SqlSemantics\Binding\Statement\Utility\OptionWords;
use SqlSemantics\Dialect;

#[CoversClass(OptionWords::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OptionWordsTest extends TestCase
{
    public function testRawReadsSignedIntegersAndKeepsOversizedNumbersAsText(): void
    {
        $identifiers = new Identifiers(Dialect::PostgreSql);
        self::assertSame(-5, OptionWords::raw(new Node('NumericOnly', 0, [new Token(0, '-', '-', 0), new Token(0, 'ICONST', '5', 1)]), $identifiers));
        self::assertSame('2147483648', OptionWords::raw(new Node('NumericOnly', 0, [new Token(0, 'FCONST', '2147483648', 0)]), $identifiers));
        self::assertSame('-1.5', OptionWords::raw(new Node('NumericOnly', 0, [new Token(0, '-', '-', 0), new Token(0, 'FCONST', '1.5', 1)]), $identifiers));
        self::assertSame('Mixed', OptionWords::raw(new Node('opt_boolean_or_string', 0, [new Token(0, 'IDENT', '"Mixed"', 0)]), $identifiers));
    }

    #[TestWith(['TRUE_P', 'TRUE', 'true'])]
    #[TestWith(['ON', 'ON', 'on'])]
    #[TestWith(['IDENT', 'Off', 'off'])]
    #[TestWith(['SCONST', "E'a\\tb'", "a\tb"])]
    #[TestWith(['SCONST', '$$x$$', 'x'])]
    public function testWordDecodesKeywordsIdentifiersAndStrings(string $name, string $text, string $expected): void
    {
        self::assertSame($expected, OptionWords::word(new Token(0, $name, $text, 0), new Identifiers(Dialect::PostgreSql)));
    }

    #[TestWith(['42', 42])]
    #[TestWith(['1_000', 1000])]
    #[TestWith(['0x1F', 31])]
    #[TestWith(['0o17', 15])]
    #[TestWith(['0b101', 5])]
    #[TestWith(['1.5', null])]
    public function testIntegerReadsEveryIntegerNotation(string $text, ?int $expected): void
    {
        self::assertSame($expected, OptionWords::integer($text));
    }

    #[TestWith([1, true])]
    #[TestWith([0, false])]
    #[TestWith([2, null])]
    #[TestWith(['ON', true])]
    #[TestWith(['off', false])]
    #[TestWith(['yes', null])]
    public function testBooleanFollowsTheServerBooleanReader(string|int $raw, ?bool $expected): void
    {
        self::assertSame($expected, OptionWords::boolean($raw));
    }
}
