<?php

declare(strict_types=1);

namespace Tests\Unit\Syntax;

use BisonParser\Ast\Declaration\Flag;
use BisonParser\Ast\Declaration\Prologue;
use BisonParser\Ast\Declaration\Start;
use BisonParser\Ast\Epilogue;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Line;
use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Alternative;
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
use BisonParser\Syntax\GrammarParser;
use BisonParser\Syntax\RuleParser;
use BisonParser\Syntax\SymbolListParser;
use BisonParser\Syntax\TokenStream;
use BisonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GrammarParser::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(DeclarationParser::class)]
#[UsesClass(Directives::class)]
#[UsesClass(Epilogue::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(Flag::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(Line::class)]
#[UsesClass(Location::class)]
#[UsesClass(Prologue::class)]
#[UsesClass(Rule::class)]
#[UsesClass(RuleParser::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Start::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolItem::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SymbolListParser::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[Small]
final class GrammarParserTest extends TestCase
{
    public function testParse(): void
    {
        $file = (new GrammarParser())->parse(new TokenStream((new Scanner())->scan("%{ int x; %}\n%debug;\n%%\n;\na: b;\n%start a;\nb: ;\n%%\nint main() {}\n")));

        self::assertSame([Prologue::class, Flag::class], array_map(static fn (object $declaration): string => $declaration::class, $file->declarations));
        self::assertSame([Rule::class, Start::class, Rule::class], array_map(static fn (object $item): string => $item::class, $file->grammar));
        self::assertSame("\nint main() {}\n", $file->epilogue?->code);
    }

    public function testParseKeepsLineDirectivesAmongTheRules(): void
    {
        $file = (new GrammarParser())->parse(new TokenStream((new Scanner())->scan("#line 3 \"a.y\"\n%debug\n%%\n#line 9\na: ;\n")));

        self::assertSame([Line::class, Flag::class], array_map(static fn (object $declaration): string => $declaration::class, $file->declarations));
        self::assertSame([Line::class, Rule::class], array_map(static fn (object $item): string => $item::class, $file->grammar));
    }

    public function testParseWithoutAnEpilogue(): void
    {
        $file = (new GrammarParser())->parse(new TokenStream((new Scanner())->scan('%% a: ; %%')));

        self::assertCount(1, $file->rules());
        self::assertSame('', $file->epilogue?->code);
        self::assertNull((new GrammarParser())->parse(new TokenStream((new Scanner())->scan('%% a: ;')))->epilogue);
    }

    public function testParseRejectsARuleBeforeTheSection(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a declaration or '%%' but found 'a' at 1:1");

        (new GrammarParser())->parse(new TokenStream((new Scanner())->scan('a: ;')));
    }

    public function testParseRejectsAStrayTokenAmongTheRules(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a rule or ';' but found '|' at 1:4");

        (new GrammarParser())->parse(new TokenStream((new Scanner())->scan('%% | a: ;')));
    }

    public function testParseRejectsADeclarationAmongTheRulesWithoutASemicolon(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected ';' after a declaration among the rules but found 'a' at 1:15");

        (new GrammarParser())->parse(new TokenStream((new Scanner())->scan('%% %start a   a: ;')));
    }

    public function testParseRejectsARulesSectionWithoutRules(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Expected a rule but found end of file at 3:1');

        (new GrammarParser())->parse(new TokenStream((new Scanner())->scan("%debug\n%%\n")));
    }

    public function testParseRejectsARulesSectionHoldingOnlyDeclarations(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a rule but found '%%' at 1:13");

        (new GrammarParser())->parse(new TokenStream((new Scanner())->scan('%% %start a;%%')));
    }
}
