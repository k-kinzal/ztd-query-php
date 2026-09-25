<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Scalar\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Scalar\Intrinsic\TextLiteralBinder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Scalar\Value\IntroducedLiteral;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\SchemaBuilder;

#[CoversClass(TextLiteralBinder::class)]
#[Medium]
final class TextLiteralBinderTest extends TestCase
{
    /**
     * @param class-string<object> $class
     */
    #[TestWith(["_utf8mb4 X'2f'", IntroducedLiteral::class, "_utf8mb4 X'2f'"])]
    #[TestWith(["_utf8 'a' 'b'", IntroducedLiteral::class, "_utf8 'ab'"])]
    #[TestWith(["'a' 'b'", Literal::class, "'ab'"])]
    public function testBindReadsIntroducersAndAdjacentStrings(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('SELECT ' . $sql);
        self::assertInstanceOf(BoundSelect::class, $query);
        self::assertInstanceOf($class, $query->outputs[0]->expression);
        self::assertSame('SELECT ' . $expected, (new \SqlSemantics\SimpleSerializer())->serialize($query));
    }

    public function testBindLeavesOtherDialectsAlone(): void
    {
        $tree = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("SELECT _utf8 'a'");
        $scope = new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::PostgreSql));
        self::assertNull(TextLiteralBinder::bind($tree->find('text_literal')[0], $scope));
    }

    /**
     * @param non-empty-list<string> $parts
     */
    #[TestWith([["'a'", "'b'"], "'ab'"])]
    #[TestWith([["N'a'", "'b'"], "N'ab'"])]
    #[TestWith([["'a'", '"b\'c"', "'d'"], "'ab''cd'"])]
    public function testJoinedReadsAdjacentPartsAsOneString(array $parts, string $expected): void
    {
        $source = new \SqlParser\Parser\Node('text_literal', 0, []);
        $tokens = array_map(static fn (string $part): \SqlParser\Lexer\Token => new \SqlParser\Lexer\Token(0, 'TEXT_STRING', $part, 0), $parts);
        self::assertSame($expected, TextLiteralBinder::joined($source, $tokens)->text);
    }

    #[TestWith(["'a''b'", "a''b"])]
    #[TestWith(['"a""b"', 'a"b'])]
    #[TestWith(['"it\'s"', "it''s"])]
    #[TestWith(['"it\\\'s"', "it\\'s"])]
    public function testContentKeepsEscapesInSingleQuoteForm(string $quoted, string $expected): void
    {
        self::assertSame($expected, TextLiteralBinder::content($quoted));
    }

    #[TestWith(["select _UTF8MB4 'a' 'b'", "SELECT _utf8mb4 'ab'"])]
    #[TestWith(["select n'a' 'b'", "SELECT N'ab'"])]
    #[TestWith(["select 'a' 'b'", "SELECT 'ab'"])]
    public function testBindWritesIntroducedAndNationalStringsBack(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql)));
    }
}
