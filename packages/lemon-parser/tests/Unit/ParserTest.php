<?php

declare(strict_types=1);

namespace Tests\Unit;

use LemonParser\Ast\CodeBlock;
use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Associativity;
use LemonParser\Ast\Declaration\Directive;
use LemonParser\Ast\Declaration\DirectiveKeyword;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\GrammarFile;
use LemonParser\Ast\Location;
use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Parser;
use LemonParser\Preprocessor\Condition;
use LemonParser\Preprocessor\Exclusion;
use LemonParser\Preprocessor\Preprocessor;
use LemonParser\Scanner\CodeReader;
use LemonParser\Scanner\Cursor;
use LemonParser\Scanner\Scanner;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\Syntax\DeclarationReader;
use LemonParser\Syntax\GrammarReader;
use LemonParser\Syntax\RuleReader;
use LemonParser\Syntax\SymbolListReader;
use LemonParser\Syntax\SymbolRegistry;
use LemonParser\Syntax\TokenStream;
use LemonParser\SyntaxException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Parser::class)]
#[UsesClass(ArgumentForm::class)]
#[UsesClass(Associativity::class)]
#[UsesClass(CodeBlock::class)]
#[UsesClass(CodeReader::class)]
#[UsesClass(Condition::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(DeclarationReader::class)]
#[UsesClass(Directive::class)]
#[UsesClass(DirectiveKeyword::class)]
#[UsesClass(Exclusion::class)]
#[UsesClass(GrammarFile::class)]
#[UsesClass(GrammarReader::class)]
#[UsesClass(Location::class)]
#[UsesClass(PrecedenceDeclaration::class)]
#[UsesClass(Preprocessor::class)]
#[UsesClass(RhsItem::class)]
#[UsesClass(Rule::class)]
#[UsesClass(RuleReader::class)]
#[UsesClass(Scanner::class)]
#[UsesClass(Symbol::class)]
#[UsesClass(SymbolListReader::class)]
#[UsesClass(SymbolRegistry::class)]
#[UsesClass(SyntaxException::class)]
#[UsesClass(Token::class)]
#[UsesClass(TokenKind::class)]
#[UsesClass(TokenStream::class)]
#[Small]
final class ParserTest extends TestCase
{
    public function testParse(): void
    {
        $source = "%token_prefix TK_\n%left PLUS.\n%ifdef EXTRA\nexpr(A) ::= expr(B) PLUS expr(C). { A = B + C; }\n%endif\nexpr(A) ::= NUM(B). { A = B; }\n";
        $parser = new Parser();

        $plain = $parser->parse($source);
        $extra = $parser->parse($source, ['EXTRA']);

        self::assertSame([Directive::class, PrecedenceDeclaration::class, Rule::class], array_map(static fn (object $item): string => $item::class, $plain->items));
        self::assertSame([1, 2], [count($plain->rules()), count($extra->rules())]);
        self::assertSame('6:1', (string) $plain->rules()[0]->location);
        self::assertSame(' A = B + C; ', $extra->rules()[0]->code?->code);
    }

    public function testParseRejectsWhatLemonRejects(): void
    {
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage('Unknown declaration keyword: "%tokens". at 1:2');

        (new Parser())->parse("%tokens A.\n");
    }
}
