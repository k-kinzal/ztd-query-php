<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Scanner\CodeReader;
use LemonParser\Scanner\Cursor;
use LemonParser\Scanner\Scanner;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\Syntax\RuleReader;
use LemonParser\Syntax\SymbolRegistry;
use LemonParser\Syntax\TokenStream;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleReader::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Location::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(SymbolRegistry::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(Rule::class)]
#[UsesClass(Symbol::class)]
#[Small]
final class RuleReaderTest extends TestCase
{
    public function testRead(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr(A) ::= expr(B) PLUS|MINUS expr(C) . next'));
        $registry = new SymbolRegistry();

        $rule = (new RuleReader())->read($stream->next(), $stream, $registry);

        self::assertSame(['expr', 'A', '1:1'], [$rule->lhs->name, $rule->lhsAlias, (string) $rule->location]);
        self::assertSame([['expr', 'B'], ['PLUS', 'MINUS'], ['expr', 'C']], array_map(static fn (RhsItem $item): array => [...array_map(static fn (Symbol $symbol): string => $symbol->name, $item->symbols), ...($item->alias === null ? [] : [$item->alias])], $rule->items));
        self::assertSame(['1:13', '1:21', '1:32'], array_map(static fn (RhsItem $item): string => (string) $item->location(), $rule->items));
        self::assertNull($rule->code);
        self::assertTrue($registry->isKnown('MINUS'));
        self::assertSame('next', $stream->peek()->text);
    }

    public function testReadAcceptsAnEmptyRightHandSide(): void
    {
        $stream = new TokenStream((new Scanner())->scan('empty ::= .'));

        $rule = (new RuleReader())->read($stream->next(), $stream, new SymbolRegistry());

        self::assertSame([], $rule->items);
        self::assertTrue($stream->eof());
    }

    public function testReadRejectsAnIllegalCharacter(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr ::= expr ? expr.'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Illegal character on RHS of rule: "?". at 1:15');

        (new RuleReader())->read($stream->next(), $stream, new SymbolRegistry());
    }

    public function testReadRejectsACompoundAtTheStart(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr ::= |PLUS expr.'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Illegal character on RHS of rule: "|PLUS". at 1:10');

        (new RuleReader())->read($stream->next(), $stream, new SymbolRegistry());
    }

    public function testReadRejectsALowerCaseCompound(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr ::= PLUS|minus expr.'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Illegal character on RHS of rule: "|minus". at 1:14');

        (new RuleReader())->read($stream->next(), $stream, new SymbolRegistry());
    }

    public function testReadRejectsTheEndOfTheFile(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr ::= expr PLUS'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Rule "expr" is not terminated by "." before the end of the file. at 1:1');

        (new RuleReader())->read($stream->next(), $stream, new SymbolRegistry());
    }

    public function testHead(): void
    {
        $reader = new RuleReader();
        $plain = new TokenStream((new Scanner())->scan('expr ::= A.'));
        $aliased = new TokenStream((new Scanner())->scan('expr ( A ) ::= B.'));

        self::assertNull($reader->head($plain->next(), $plain));
        self::assertSame('A', $reader->head($aliased->next(), $aliased));
        self::assertSame('B', $aliased->peek()->text);
    }

    public function testHeadRejectsAMissingArrow(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr : A.'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected to see a ":" following the LHS symbol "expr". at 1:6');

        (new RuleReader())->head($stream->next(), $stream);
    }

    public function testHeadRejectsABadAlias(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr(1) ::= A.'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('"1" is not a valid alias for the LHS "expr" at 1:6');

        (new RuleReader())->head($stream->next(), $stream);
    }

    public function testHeadRejectsAnUnclosedAlias(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr(A ::= A.'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing ")" following LHS alias name "A". at 1:8');

        (new RuleReader())->head($stream->next(), $stream);
    }

    public function testHeadRejectsAMissingArrowAfterTheAlias(): void
    {
        $stream = new TokenStream((new Scanner())->scan('expr(A) A.'));

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing "->" following: "expr(A)". at 1:9');

        (new RuleReader())->head($stream->next(), $stream);
    }

    public function testCompound(): void
    {
        $item = new RhsItem([new Symbol('PLUS', new Location(1, 10))], 'X');

        $extended = (new RuleReader())->compound($item, new Token(TokenKind::Compound, 'MINUS', new Location(1, 14), '|MINUS'));

        self::assertSame(['PLUS', 'MINUS'], array_map(static fn (Symbol $symbol): string => $symbol->name, $extended->symbols));
        self::assertSame('X', $extended->alias);
    }

    public function testCompoundRejectsANonterminal(): void
    {
        $item = new RhsItem([new Symbol('expr', new Location(1, 10))], null);

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Cannot form a compound containing a non-terminal at 1:14');

        (new RuleReader())->compound($item, new Token(TokenKind::Compound, 'MINUS', new Location(1, 14), '|MINUS'));
    }

    public function testAlias(): void
    {
        $stream = new TokenStream((new Scanner())->scan('X) rest'));
        $lhs = new Token(TokenKind::Word, 'expr', new Location(1, 1), 'expr');

        $named = (new RuleReader())->alias(new RhsItem([new Symbol('NUM', new Location(1, 10))], null), $stream, $lhs, null);

        self::assertSame('X', $named->alias);
        self::assertSame('rest', $stream->peek()->text);
    }

    public function testAliasRejectsABadName(): void
    {
        $stream = new TokenStream((new Scanner())->scan('1)'));
        $lhs = new Token(TokenKind::Word, 'expr', new Location(1, 1), 'expr');

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('"1" is not a valid alias for the RHS symbol "NUM" at 1:1');

        (new RuleReader())->alias(new RhsItem([new Symbol('NUM', new Location(1, 10))], null), $stream, $lhs, null);
    }

    public function testAliasRejectsAMissingCloser(): void
    {
        $stream = new TokenStream((new Scanner())->scan('X .'));
        $lhs = new Token(TokenKind::Word, 'expr', new Location(1, 1), 'expr');

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Missing ")" following LHS alias name "A". at 1:3');

        (new RuleReader())->alias(new RhsItem([new Symbol('NUM', new Location(1, 10))], null), $stream, $lhs, 'A');
    }
}
