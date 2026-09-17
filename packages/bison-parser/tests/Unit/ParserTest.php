<?php

declare(strict_types=1);

namespace Tests\Unit;

use BisonParser\Ast\Declaration\Prologue;
use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Declaration\Symbols\Associativity;
use BisonParser\Ast\Declaration\Symbols\PrecedenceDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolClass;
use BisonParser\Ast\Declaration\Symbols\SymbolDeclaration;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Epilogue;
use BisonParser\Ast\GrammarFile;
use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Parser;
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

#[CoversClass(Parser::class)]
#[UsesClass(Action::class)]
#[UsesClass(Alias::class)]
#[UsesClass(Alternative::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(DeclarationParser::class)]
#[UsesClass(Directives::class)]
#[UsesClass(Epilogue::class)]
#[UsesClass(Escapes::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(GrammarParser::class)]
#[UsesClass(Location::class)]
#[UsesClass(PrecItem::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(Prologue::class)]
#[UsesClass(Rule::class)]
#[UsesClass(RuleParser::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolClass::class)]
#[UsesClass(SymbolDeclaration::class)]
#[UsesClass(SymbolEntry::class)]
#[UsesClass(SymbolItem::class)]
#[UsesClass(SymbolKind::class)]
#[UsesClass(SymbolListParser::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[Small]
final class ParserTest extends TestCase
{
    public function testParse(): void
    {
        $file = (new Parser())->parse(implode("\n", [
            '%{ #include <stdio.h> %}',
            '%token <int> NUM 258 "number"',
            "%left '+' '-'",
            '%%',
            "expr: expr '+' expr { \$\$ = \$1 + \$3; }",
            '    | NUM %prec UMINUS',
            '    ;',
            '%%',
            'int main() {}',
        ]));

        self::assertSame([Prologue::class, SymbolDeclaration::class, PrecedenceDeclaration::class], array_map(static fn (object $declaration): string => $declaration::class, $file->declarations));
        self::assertSame('NUM', $file->rules()[0]->alternatives[1]->symbols()[0]->value);
        self::assertSame([SymbolItem::class, SymbolItem::class, SymbolItem::class, Action::class], array_map(static fn (object $item): string => $item::class, $file->rules()[0]->alternatives[0]->items));
        self::assertSame("\nint main() {}", $file->epilogue?->code);
    }

    public function testParseRejectsAGrammarBisonRejects(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Expected a declaration or '%%' but found 'expr' at 2:1");

        (new Parser())->parse("%token NUM\nexpr: NUM;");
    }
}
