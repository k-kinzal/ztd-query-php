<?php

declare(strict_types=1);

namespace Tests\Unit\Ast\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Ast\DialectParser;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Dialect;
use SqlSemantics\Type\Identity\Numeric\NumericParameter;

#[CoversClass(\SqlSemantics\Ast\Type\TypeTokens::class)]
#[Medium]
final class TypeTokensTest extends TestCase
{
    public function testOuterDoesNotConfuseNestedModifierIdentifiersWithTypeWords(): void
    {
        $source = (new DialectParser(Dialect::PostgreSql))->parse('CREATE TABLE t(x numeric(("UNSIGNED")))');
        $type = Tree::outer($source, ['Typename'])[0];
        self::assertSame(['numeric'], array_map(static fn ($token): string => $token->text, \SqlSemantics\Ast\Type\TypeTokens::outer($type)));
    }

    public function testNumbersRetainsUnsignedPrecisionAndScale(): void
    {
        $source = (new DialectParser(Dialect::MySql))->parse('CREATE TABLE t(x DECIMAL(12, 2) UNSIGNED)');
        $type = Tree::outer($source, ['type'])[0];
        self::assertSame(['12', '2'], array_map(static fn (NumericParameter $parameter): string => $parameter->spelling, \SqlSemantics\Ast\Type\TypeTokens::numbers($type)));
    }

    public function testOuterSkipsEveryNestedParameterGroup(): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), ['a', '(', '(', 'b', ')', 'c', ')', 'd']);
        self::assertSame(['a', 'd'], array_map(static fn ($token): string => $token->text, \SqlSemantics\Ast\Type\TypeTokens::outer(new \SqlParser\Parser\Node('type', 0, $tokens))));
    }

    /**
     * @param list<string> $texts
     * @param list<string> $expected
     */
    #[TestWith([['enum', '(', "'a'", ')'], []])]
    #[TestWith([['decimal', '(', '(', '1', ')', ')'], ['1']])]
    #[TestWith([['decimal', '(', ')'], []])]
    #[TestWith([['a', '(', '1', ')', ',', '2'], ['1']])]
    #[TestWith([['x', '(', '-', '5', ',', '2', ')'], ['-5', '2']])]
    public function testNumbersReadsOnlyTheFirstLevelOperands(array $texts, array $expected): void
    {
        $tokens = array_map(static fn (string $text): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'IDENT', $text, 0), $texts);
        self::assertSame($expected, array_map(static fn (NumericParameter $parameter): string => $parameter->spelling, \SqlSemantics\Ast\Type\TypeTokens::numbers(new \SqlParser\Parser\Node('type', 0, $tokens))));
    }
}
