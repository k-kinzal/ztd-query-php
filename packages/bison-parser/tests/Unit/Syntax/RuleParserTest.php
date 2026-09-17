<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use BisonParser\Ast\Line;
use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\DprecItem;
use BisonParser\Ast\Rule\EmptyItem;
use BisonParser\Ast\Rule\ExpectItem;
use BisonParser\Ast\Rule\MergeItem;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Predicate;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Scanner\CodeReader;
use BisonParser\Scanner\Cursor;
use BisonParser\Scanner\Directives;
use BisonParser\Scanner\Escapes;
use BisonParser\Scanner\Scanner;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\Syntax\DeclarationParser;
use BisonParser\Syntax\RuleParser;
use BisonParser\Syntax\SymbolListParser;
use BisonParser\Syntax\TokenStream;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RuleParser::class)]
#[UsesClass(Action::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Directives::class)]
#[UsesClass(DprecItem::class)]
#[UsesClass(EmptyItem::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(ExpectItem::class)]
#[UsesClass(Line::class)]
#[UsesClass(DeclarationParser::class)]
#[UsesClass(Location::class)]
#[UsesClass(MergeItem::class)]
#[UsesClass(PrecItem::class)]
#[UsesClass(Predicate::class)]
#[UsesClass(Rule::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolItem::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SymbolListParser::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[Small]
final class RuleParserTest extends TestCase
{
    public function testParse(): void
    {
        $stream = new TokenStream((new Scanner())->scan("expr[e]: expr '+' expr { \$\$ = \$1 + \$3; }\n  | NUM\n  | %empty ;; next: ;"));

        $rule = (new RuleParser())->parse($stream);

        self::assertSame(['expr', 'e', '1:1'], [$rule->name->value, $rule->namedReference, (string) $rule->location]);
        self::assertSame([4, 1, 1], array_map(static fn (Alternative $alternative): int => count($alternative->items), $rule->alternatives));
        self::assertSame(['1:10', '2:5', '3:5'], array_map(static fn (Alternative $alternative): string => (string) $alternative->location, $rule->alternatives));
        self::assertTrue($stream->is(TokenKind::IdentifierColon));
    }

    public function testParseAcceptsSemicolonsBetweenAlternatives(): void
    {
        $stream = new TokenStream((new Scanner())->scan("a: %empty;|b;;|c;;;\nd: ;"));

        $rule = (new RuleParser())->parse($stream);

        self::assertSame([1, 1, 1], array_map(static fn (Alternative $alternative): int => count($alternative->items), $rule->alternatives));
        self::assertSame('d', $stream->peek()->text);
    }

    public function testParseEndsAtTheNextRule(): void
    {
        $stream = new TokenStream((new Scanner())->scan("a: b c\nd: e"));

        $rule = (new RuleParser())->parse($stream);

        self::assertCount(2, $rule->alternatives[0]->items);
        self::assertSame('d', $stream->peek()->text);
    }

    public function testParseRejectsAMissingColon(): void
    {
        $stream = new TokenStream([new Token(TokenKind::IdentifierColon, 'a', new Location(1, 1)), new Token(TokenKind::Pipe, '|', new Location(1, 3)), new Token(TokenKind::End, '', new Location(1, 4))]);

        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected ':' after 'a' but found '|' at 1:3");

        (new RuleParser())->parse($stream);
    }

    public function testParseRejectsANonRule(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a rule but found '%token' at 1:1");

        (new RuleParser())->parse(new TokenStream((new Scanner())->scan('%token X')));
    }

    public function testAlternative(): void
    {
        $parser = new RuleParser();
        $filled = $parser->alternative(new TokenStream((new Scanner())->scan(' a b |')), new Location(1, 1));
        $empty = $parser->alternative(new TokenStream((new Scanner())->scan('|')), new Location(1, 1));

        self::assertSame(['a', 'b'], array_map(static fn (Symbol $symbol): string => $symbol->value, $filled->symbols()));
        self::assertSame('1:2', (string) $filled->location);
        self::assertSame([], $empty->items);
        self::assertSame('1:1', (string) $empty->location);
    }

    public function testItem(): void
    {
        $parser = new RuleParser();
        $stream = new TokenStream((new Scanner())->scan('NUM[n] <int>{ x }[a] %?{ p } %empty ;'));
        $symbol = $parser->item($stream);
        $action = $parser->item($stream);
        $predicate = $parser->item($stream);
        $empty = $parser->item($stream);

        self::assertInstanceOf(SymbolItem::class, $symbol);
        self::assertSame(['NUM', 'n'], [$symbol->symbol->value, $symbol->namedReference]);
        self::assertInstanceOf(Action::class, $action);
        self::assertInstanceOf(Predicate::class, $predicate);
        self::assertInstanceOf(EmptyItem::class, $empty);
        self::assertNull($parser->item($stream));
        self::assertTrue($stream->is(TokenKind::Semicolon));
    }

    public function testItemKeepsALineDirective(): void
    {
        $stream = new TokenStream((new Scanner())->scan("A\n#line 8 \"x.y\"\nB ;"));
        $parser = new RuleParser();
        $parser->item($stream);

        $line = $parser->item($stream);

        self::assertInstanceOf(Line::class, $line);
        self::assertSame([8, 'x.y', '2:1'], [$line->line, $line->file, (string) $line->location()]);
    }

    public function testAction(): void
    {
        $parser = new RuleParser();
        $plain = $parser->action(new TokenStream((new Scanner())->scan('{ a }')));
        $tagged = $parser->action(new TokenStream((new Scanner())->scan('<int>{ b }[r]')));

        self::assertSame([null, ' a ', null, '1:1'], [$plain->tag, $plain->code, $plain->namedReference, (string) $plain->location]);
        self::assertSame(['int', ' b ', 'r', '1:1'], [$tagged->tag, $tagged->code, $tagged->namedReference, (string) $tagged->location]);
    }

    public function testActionRejectsATagWithoutCode(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected braced code after the tag but found 'x' at 1:7");

        (new RuleParser())->action(new TokenStream((new Scanner())->scan('<int> x')));
    }

    public function testModifier(): void
    {
        $parser = new RuleParser();
        $stream = new TokenStream((new Scanner())->scan('%empty %prec UMINUS %dprec 2 %merge <m> %expect 1 %expect-rr 3 %token'));
        $empty = $parser->modifier($stream);
        $prec = $parser->modifier($stream);
        $dprec = $parser->modifier($stream);
        $merge = $parser->modifier($stream);
        $expect = $parser->modifier($stream);
        $expectRr = $parser->modifier($stream);

        self::assertInstanceOf(EmptyItem::class, $empty);
        self::assertInstanceOf(PrecItem::class, $prec);
        self::assertSame('UMINUS', $prec->symbol->value);
        self::assertInstanceOf(DprecItem::class, $dprec);
        self::assertSame(2, $dprec->value);
        self::assertInstanceOf(MergeItem::class, $merge);
        self::assertSame('m', $merge->tag);
        self::assertInstanceOf(ExpectItem::class, $expect);
        self::assertSame([1, false], [$expect->count, $expect->reduceReduce]);
        self::assertInstanceOf(ExpectItem::class, $expectRr);
        self::assertSame([3, true], [$expectRr->count, $expectRr->reduceReduce]);
        self::assertNull($parser->modifier($stream));
        self::assertTrue($stream->isDirective('token'));
    }

    public function testModifierRejectsADprecWithoutANumber(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a number after %dprec but found ';' at 1:8");

        (new RuleParser())->modifier(new TokenStream((new Scanner())->scan('%dprec ;')));
    }

    public function testModifierRejectsAMergeWithoutATag(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a tag after %merge but found 'x' at 1:8");

        (new RuleParser())->modifier(new TokenStream((new Scanner())->scan('%merge x')));
    }

    public function testSymbolToken(): void
    {
        $stream = new TokenStream((new Scanner())->scan("'+' ;"));

        self::assertSame('+', (new RuleParser())->symbolToken($stream)->text);
        self::assertTrue($stream->is(TokenKind::Semicolon));
    }

    public function testSymbolTokenRejectsANonSymbol(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a symbol after %prec but found ';' at 1:1");

        (new RuleParser())->symbolToken(new TokenStream((new Scanner())->scan(';')));
    }
}
