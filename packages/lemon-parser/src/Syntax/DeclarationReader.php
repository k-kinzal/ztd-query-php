<?php

declare(strict_types=1);

namespace LemonParser\Syntax;

use LemonParser\Ast\Declaration\ArgumentForm;
use LemonParser\Ast\Declaration\Associativity;
use LemonParser\Ast\Declaration\Declaration;
use LemonParser\Ast\Declaration\Destructor;
use LemonParser\Ast\Declaration\Directive;
use LemonParser\Ast\Declaration\DirectiveKeyword;
use LemonParser\Ast\Declaration\Fallback;
use LemonParser\Ast\Declaration\PrecedenceDeclaration;
use LemonParser\Ast\Declaration\TokenClass;
use LemonParser\Ast\Declaration\TokenDeclaration;
use LemonParser\Ast\Declaration\TypeDeclaration;
use LemonParser\Ast\Declaration\Wildcard;
use LemonParser\Ast\Location;
use LemonParser\Ast\Symbol;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\SyntaxException;

/**
 * Reads a declaration after its `%` sign, as Lemon's declaration states do.
 *
 * @visibility root
 */
final class DeclarationReader
{
    /**
     * @param SymbolListReader $lists Reads lists of terminals ended by a period
     */
    public function __construct(private readonly SymbolListReader $lists = new SymbolListReader())
    {
    }

    /**
     * Reads the declaration whose `%` has been taken.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     * @param Location $at Where the `%` is
     *
     * @return Declaration The declaration
     *
     * @throws SyntaxException Where Lemon reports an error
     */
    public function read(TokenStream $tokens, SymbolRegistry $registry, Location $at): Declaration
    {
        $keyword = $tokens->next();
        if (!$keyword->isAlphaWord()) {
            throw new SyntaxException("Illegal declaration keyword: \"{$keyword->raw}\".", $keyword->location);
        }
        $directive = DirectiveKeyword::tryFrom($keyword->text);
        if ($directive !== null) {
            [$value, $form] = $this->argument($tokens, $keyword);

            return new Directive($directive, $value, $form, $at);
        }
        $associativity = Associativity::tryFrom($keyword->text);
        if ($associativity !== null) {
            return new PrecedenceDeclaration($associativity, $this->lists->ranked($tokens, $registry), $at);
        }

        return match ($keyword->text) {
            'destructor' => $this->readDestructor($tokens, $registry, $at),
            'type' => $this->readType($tokens, $registry, $at),
            'fallback' => new Fallback($this->lists->fallbacks($tokens, $registry), $at),
            'token' => new TokenDeclaration($this->lists->tokens($tokens, $registry, 'token'), $at),
            'wildcard' => new Wildcard($this->lists->wildcard($tokens, $registry), $at),
            'token_class' => $this->readTokenClass($tokens, $registry, $at),
            default => throw new SyntaxException("Unknown declaration keyword: \"%{$keyword->text}\".", $keyword->location),
        };
    }

    /**
     * Reads the one argument of a keyword: braced code, a string or a word.
     *
     * @param TokenStream $tokens The rest
     * @param Token $keyword The keyword, for messages
     *
     * @return array{string, ArgumentForm} The argument without delimiters and how it was written
     *
     * @throws SyntaxException When what follows is none of the three
     */
    public function argument(TokenStream $tokens, Token $keyword): array
    {
        $token = $tokens->next();
        $form = match ($token->kind) {
            TokenKind::Code => ArgumentForm::Code,
            TokenKind::String => ArgumentForm::String,
            TokenKind::Word => ArgumentForm::Word,
            TokenKind::Arrow, TokenKind::Compound, TokenKind::Punctuation, TokenKind::End => null,
        };
        if ($form === null) {
            throw new SyntaxException("Illegal argument to %{$keyword->text}: {$token->raw}", $token->location);
        }

        return [$token->text, $form];
    }

    /**
     * Reads `%destructor symbol argument`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     * @param Location $at Where the `%` is
     *
     * @return Destructor The declaration
     *
     * @throws SyntaxException When no symbol follows
     */
    public function readDestructor(TokenStream $tokens, SymbolRegistry $registry, Location $at): Destructor
    {
        $symbol = $tokens->next();
        if (!$symbol->isAlphaWord()) {
            throw new SyntaxException('Symbol name missing after %destructor keyword', $symbol->location);
        }
        $registry->see($symbol->text);
        [$value, $form] = $this->argument($tokens, $symbol);

        return new Destructor(new Symbol($symbol->text, $symbol->location), $value, $form, $at);
    }

    /**
     * Reads `%type symbol argument`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     * @param Location $at Where the `%` is
     *
     * @return TypeDeclaration The declaration
     *
     * @throws SyntaxException When no symbol follows or it is already typed
     */
    public function readType(TokenStream $tokens, SymbolRegistry $registry, Location $at): TypeDeclaration
    {
        $symbol = $tokens->next();
        if (!$symbol->isAlphaWord()) {
            throw new SyntaxException('Symbol name missing after %type keyword', $symbol->location);
        }
        $registry->type($symbol->text, $symbol->location);
        [$value, $form] = $this->argument($tokens, $symbol);

        return new TypeDeclaration(new Symbol($symbol->text, $symbol->location), $value, $form, $at);
    }

    /**
     * Reads `%token_class name TOKEN|TOKEN... .`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     * @param Location $at Where the `%` is
     *
     * @return TokenClass The declaration
     *
     * @throws SyntaxException When the name is not a fresh nonterminal name
     */
    public function readTokenClass(TokenStream $tokens, SymbolRegistry $registry, Location $at): TokenClass
    {
        $name = $tokens->next();
        if (!$name->isLowerWord()) {
            throw new SyntaxException("%token_class must be followed by an identifier: {$name->raw}", $name->location);
        }
        if ($registry->isKnown($name->text)) {
            throw new SyntaxException("Symbol \"{$name->text}\" already used", $name->location);
        }
        $registry->see($name->text);

        return new TokenClass(new Symbol($name->text, $name->location), $this->lists->classTokens($tokens, $registry), $at);
    }
}
