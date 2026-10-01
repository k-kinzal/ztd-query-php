<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;

#[CoversClass(\SqlSemantics\Platform\MySql\LiteralDecoder::class)]
#[Medium]
final class LiteralDecoderTest extends TestCase
{
    #[TestWith(["'a''b'", "a'b"])]
    #[TestWith(["'a' 'b'", 'ab'])]
    #[TestWith(["N'a\\nb'", "a\nb"])]
    #[TestWith(["'a\\%b\\_c\\z'", 'a\\%b\\_cz'])]
    #[TestWith(["X'0041'", "\0A"])]
    #[TestWith(['0xF', "\x0f"])]
    #[TestWith(["b'000000001'", "\0\1"])]
    #[TestWith(["b''", ''])]
    #[TestWith(['18446744073709551616', '18446744073709551616'])]
    #[TestWith(['1.000000000000000001', '1.000000000000000001'])]
    #[TestWith(['TRUE', true])]
    #[TestWith(['FALSE', false])]
    #[TestWith(['NULL', null])]

    public function testDecodePreservesTheLiteralValue(string $sql, string|bool|null $expected): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize($sql), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        self::assertSame($expected, $semantics->language()->dialect->platform()->literals($semantics->language())->decode($tokens)->value());
    }

    #[TestWith(['1 + 2'])]
    #[TestWith(['CURRENT_TIMESTAMP'])]
    #[TestWith(['some_name'])]
    public function testDecodeRejectsValuesRequiringEvaluation(string $sql): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize($sql), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        $this->expectException(DecodingException::class);
        $semantics->language()->dialect->platform()->literals($semantics->language())->decode($tokens);
    }

    public function testDecodeUsesNoBackslashEscapesAndPreservesIntroducers(): void
    {
        $semantics = new Semantics(Dialect::MySql, mode: \SqlSemantics\Platform\MySql\Mode::fromString('NO_BACKSLASH_ESCAPES'));
        $column = $semantics->analyze("CREATE TABLE t(a ENUM('a\\nb', X'41'))", [])->resolution?->declarations[0]->columns[0] ?? self::fail('Missing column');
        self::assertSame('a\\nb', $semantics->decodeLiteral($column->type->members[0])->value());
        self::assertSame('A', $semantics->decodeLiteral($column->type->members[1])->value());
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize("_utf8mb4 X'41'"), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        $literal = $semantics->language()->dialect->platform()->literals($semantics->language())->decode($tokens);
        self::assertInstanceOf(\SqlSemantics\Statement\Literal\StringLiteral::class, $literal);
        self::assertSame('utf8mb4', $literal->characterSet);
    }


    public function testBytesDecodesAWholeBinaryToken(): void
    {
        $language = (new Semantics(Dialect::MySql))->language();
        $decoder = new \SqlSemantics\Platform\MySql\LiteralDecoder($language);
        self::assertSame('A', $decoder->bytes($language->parser()->tokenize("X'41'")[0]));
    }
    public function testStringHonorsItsSessionMode(): void
    {
        $language = (new Semantics(Dialect::MySql))->language();
        self::assertSame("\n", (new \SqlSemantics\Platform\MySql\LiteralDecoder($language))->string($language->parser()->tokenize("'\\n'")[0]));
    }

}
