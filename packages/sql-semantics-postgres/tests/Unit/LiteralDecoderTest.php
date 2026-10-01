<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\PostgreSql\Dialect;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\LiteralDecoder::class)]
#[Medium]
final class LiteralDecoderTest extends TestCase
{
    #[TestWith(["'a''b'", "a'b"])]
    #[TestWith(["'a'\n-- 'ignored'\n'b'", 'ab'])]
    #[TestWith(["E'a\\nb\\x41\\101'", "a\nbAA"])]
    #[TestWith(["E'\\uD83D\\uDE00'", '😀'])]
    #[TestWith(["U&'d\\0061ta'", 'data'])]
    #[TestWith(["U&'d!0061ta' UESCAPE '!'", 'data'])]
    #[TestWith(["U&'\\+01F600'", '😀'])]
    #[TestWith(['$tag$a\\b$tag$', 'a\\b'])]
    #[TestWith(["B'001'", '001'])]
    #[TestWith(["B''", ''])]
    #[TestWith(["X''", ''])]
    #[TestWith(["X'041'", '000001000001'])]
    #[TestWith(['0x10000000000000000', '18446744073709551616'])]
    #[TestWith(['1_000', '1000'])]
    #[TestWith(['1.000000000000000001', '1.000000000000000001'])]
    #[TestWith(['TRUE', true])]
    #[TestWith(['FALSE', false])]
    #[TestWith(['NULL', null])]

    public function testDecodePreservesTheLiteralValue(string $sql, string|bool|null $expected): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize($sql), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        self::assertSame($expected, $semantics->language()->dialect->platform()->literals($semantics->language())->decode($tokens)->value());
    }

    #[TestWith(['1 + 2'])]
    #[TestWith(['CURRENT_TIMESTAMP'])]
    #[TestWith(['some_name'])]
    public function testDecodeRejectsValuesRequiringEvaluation(string $sql): void
    {
        $semantics = new Semantics(Dialect::PostgreSql);
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize($sql), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        $this->expectException(DecodingException::class);
        $semantics->language()->dialect->platform()->literals($semantics->language())->decode($tokens);
    }


    public function testStringReadsDollarQuotesWithoutEscapes(): void
    {
        self::assertSame('a\\b', (new \SqlSemantics\Platform\PostgreSql\LiteralDecoder((new Semantics(Dialect::PostgreSql))->language()))->string('$tag$a\\b$tag$'));
    }


    public function testUnicodeUsesTheEscapeClauseRetainedInTheToken(): void
    {
        $decoder = new \SqlSemantics\Platform\PostgreSql\LiteralDecoder((new Semantics(Dialect::PostgreSql))->language());
        self::assertSame('data', $decoder->unicode("U&'d!0061ta' UESCAPE '!'"));
    }

}
