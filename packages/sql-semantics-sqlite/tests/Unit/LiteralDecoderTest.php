<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Core\Literal\DecodingException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\Sqlite\Dialect;

#[CoversClass(\SqlSemantics\Platform\Sqlite\LiteralDecoder::class)]
#[Medium]
final class LiteralDecoderTest extends TestCase
{
    #[TestWith(["'a''b'", "a'b"])]
    #[TestWith(["'a\\nb'", 'a\\nb'])]
    #[TestWith(["X'0041'", "\0A"])]
    #[TestWith(['0xffffffffffffffff', '-1'])]
    #[TestWith(['0x8000000000000000', '-9223372036854775808'])]
    #[TestWith(['1_000', '1000'])]
    #[TestWith(['1.000000000000000001', '1.000000000000000001'])]
    #[TestWith(['TRUE', true])]
    #[TestWith(['FALSE', false])]
    #[TestWith(['NULL', null])]

    public function testDecodePreservesTheLiteralValue(string $sql, string|bool|null $expected): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize($sql), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        self::assertSame($expected, $semantics->language()->dialect->platform()->literals($semantics->language())->decode($tokens)->value());
    }

    #[TestWith(['1 + 2'])]
    #[TestWith(['CURRENT_TIMESTAMP'])]
    #[TestWith(['some_name'])]
    public function testDecodeRejectsValuesRequiringEvaluation(string $sql): void
    {
        $semantics = new Semantics(Dialect::Sqlite);
        $tokens = array_values(array_filter($semantics->language()->parser()->tokenize($sql), static fn ($token): bool => $token->text !== ''));
        self::assertNotEmpty($tokens);
        $this->expectException(DecodingException::class);
        $semantics->language()->dialect->platform()->literals($semantics->language())->decode($tokens);
    }


    public function testNumberPreservesSignedHexadecimalSemantics(): void
    {
        self::assertSame('-1', (new \SqlSemantics\Platform\Sqlite\LiteralDecoder())->number('0xffffffffffffffff'));
        $this->expectException(DecodingException::class);
        (new \SqlSemantics\Platform\Sqlite\LiteralDecoder())->number('0x10000000000000000');
    }

}
