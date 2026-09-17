<?php

declare(strict_types=1);

namespace BisonParser\Syntax;

use BisonParser\Ast\Declaration\Symbols\Alias;
use BisonParser\Ast\Declaration\Symbols\SymbolEntry;
use BisonParser\Ast\Symbol;
use BisonParser\Ast\SymbolKind;
use BisonParser\Ast\Tag;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\SyntaxException;

/**
 * Reads the symbol lists that follow `%token`, `%nterm`, `%type`, `%start`, `%left` and their kin.
 *
 * The lists differ in what a string means: after `%token` it is the alias
 * of the identifier before it, after `%left` and `%type` it is a token of
 * its own, and after `%token` it may also carry a token number.
 *
 * @visibility root
 */
final class SymbolListParser
{
    /**
     * Reads the entries of a `%token` or `%nterm` list, where a string aliases the identifier before it.
     *
     * @param TokenStream $tokens Tokens positioned after the directive
     *
     * @return list<SymbolEntry> The entries, at least one
     *
     * @throws SyntaxException When the list is empty or malformed
     */
    public function tokenDeclarations(TokenStream $tokens): array
    {
        return $this->entries($tokens, true, true);
    }

    /**
     * Reads the entries of a precedence list, where a string is a token of its own.
     *
     * @param TokenStream $tokens Tokens positioned after the directive
     *
     * @return list<SymbolEntry> The entries, at least one
     *
     * @throws SyntaxException When the list is empty or malformed
     */
    public function precedenceDeclarations(TokenStream $tokens): array
    {
        return $this->entries($tokens, true, false);
    }

    /**
     * Reads the entries of a `%type` list, plain symbols under optional tags.
     *
     * @param TokenStream $tokens Tokens positioned after the directive
     *
     * @return list<SymbolEntry> The entries, at least one
     *
     * @throws SyntaxException When the list is empty or malformed
     */
    public function typeDeclarations(TokenStream $tokens): array
    {
        return $this->entries($tokens, false, false);
    }

    /**
     * Reads tagged entries until something that is neither a tag nor a symbol.
     *
     * @param TokenStream $tokens Tokens positioned at the list
     * @param bool $numbered Whether an integer may follow an identifier
     * @param bool $aliased Whether a string after an identifier is its alias
     *
     * @return list<SymbolEntry> The entries, at least one
     *
     * @throws SyntaxException When the list is empty or a tag has no symbol after it
     */
    public function entries(TokenStream $tokens, bool $numbered, bool $aliased): array
    {
        $entries = [];
        $tag = null;
        while (true) {
            $tagToken = $tokens->accept(TokenKind::Tag);
            if ($tagToken !== null) {
                $tag = $tagToken->text;
                if (!$this->startsSymbol($tokens->peek())) {
                    throw SyntaxException::unexpected('a symbol after the tag', $tokens->peek()->describe(), $tokens->peek()->location);
                }
                continue;
            }
            if (!$this->startsSymbol($tokens->peek())) {
                break;
            }
            $entries[] = $this->entry($tokens, $tag, $numbered, $aliased);
        }
        if ($entries === []) {
            throw SyntaxException::unexpected('a symbol', $tokens->peek()->describe(), $tokens->peek()->location);
        }

        return $entries;
    }

    /**
     * Reads one entry: a symbol with the number and alias the list allows.
     *
     * @param TokenStream $tokens Tokens positioned on the symbol
     * @param string|null $tag Tag in force
     * @param bool $numbered Whether an integer may follow an identifier
     * @param bool $aliased Whether a string after an identifier is its alias
     *
     * @return SymbolEntry The entry
     */
    public function entry(TokenStream $tokens, ?string $tag, bool $numbered, bool $aliased): SymbolEntry
    {
        $symbol = $this->symbol($tokens->next());
        if ($symbol->kind === SymbolKind::String) {
            return new SymbolEntry($symbol, $tag, null, null);
        }
        $number = null;
        if ($numbered) {
            $integer = $tokens->accept(TokenKind::Integer);
            $number = $integer === null ? null : (int) $integer->text;
        }
        $alias = null;
        if ($aliased) {
            $string = $tokens->accept(TokenKind::String) ?? $tokens->accept(TokenKind::TranslatableString);
            $alias = $string === null ? null : new Alias($string->text, $string->is(TokenKind::TranslatableString), $string->location);
        }

        return new SymbolEntry($symbol, $tag, $number, $alias);
    }

    /**
     * Reads the symbols that follow `%start`.
     *
     * @param TokenStream $tokens Tokens positioned after the directive
     *
     * @return list<Symbol> The symbols, at least one
     *
     * @throws SyntaxException When no symbol follows
     */
    public function symbols(TokenStream $tokens): array
    {
        $symbols = [];
        while ($this->startsSymbol($tokens->peek())) {
            $symbols[] = $this->symbol($tokens->next());
        }
        if ($symbols === []) {
            throw SyntaxException::unexpected('a symbol', $tokens->peek()->describe(), $tokens->peek()->location);
        }

        return $symbols;
    }

    /**
     * Reads the symbols and tags that follow the code of `%destructor` or `%printer`.
     *
     * @param TokenStream $tokens Tokens positioned after the code
     *
     * @return list<Symbol|Tag> The targets, at least one
     *
     * @throws SyntaxException When no target follows
     */
    public function targets(TokenStream $tokens): array
    {
        $targets = [];
        while (true) {
            $token = $tokens->peek();
            if ($this->startsSymbol($token)) {
                $targets[] = $this->symbol($tokens->next());
            } elseif ($token->is(TokenKind::Tag) || $token->is(TokenKind::TagAny) || $token->is(TokenKind::TagNone)) {
                $targets[] = new Tag($tokens->next()->text, $token->location);
            } else {
                break;
            }
        }
        if ($targets === []) {
            throw SyntaxException::unexpected('a symbol or tag', $tokens->peek()->describe(), $tokens->peek()->location);
        }

        return $targets;
    }

    /**
     * Reports whether a token begins a symbol.
     *
     * @param Token $token Token to look at
     *
     * @return bool True for an identifier, a character literal or a string
     */
    public function startsSymbol(Token $token): bool
    {
        return $token->is(TokenKind::Identifier) || $token->is(TokenKind::CharLiteral) || $token->is(TokenKind::String);
    }

    /**
     * Turns a symbol token into a symbol.
     *
     * @param Token $token An identifier, a character literal or a string token
     *
     * @return Symbol The symbol
     *
     * @throws SyntaxException When the token is none of these
     */
    public function symbol(Token $token): Symbol
    {
        if ($token->is(TokenKind::Identifier) || $token->is(TokenKind::IdentifierColon)) {
            $kind = SymbolKind::Identifier;
        } elseif ($token->is(TokenKind::CharLiteral)) {
            $kind = SymbolKind::CharLiteral;
        } elseif ($token->is(TokenKind::String)) {
            $kind = SymbolKind::String;
        } else {
            throw SyntaxException::unexpected('a symbol', $token->describe(), $token->location);
        }

        return new Symbol($kind, $token->text, $token->location);
    }
}
