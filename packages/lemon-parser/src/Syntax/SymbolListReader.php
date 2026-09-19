<?php

declare(strict_types=1);

namespace LemonParser\Syntax;

use LemonParser\Ast\Symbol;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\SyntaxException;

/**
 * Reads the lists of terminals that end with a period.
 *
 * @visibility root
 */
final class SymbolListReader
{
    /**
     * Reads the terminals of `%left`, `%right` or `%nonassoc`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     *
     * @return list<Symbol> The terminals
     *
     * @throws SyntaxException When something is not a terminal or already ranked
     */
    public function ranked(TokenStream $tokens, SymbolRegistry $registry): array
    {
        $symbols = [];
        foreach ($this->untilPeriod($tokens) as $token) {
            if (!$token->isUpperWord()) {
                throw new SyntaxException("Can't assign a precedence to \"{$token->raw}\".", $token->location);
            }
            $registry->rank($token->text, $token->location);
            $symbols[] = new Symbol($token->text, $token->location);
        }

        return $symbols;
    }

    /**
     * Reads the terminals of `%fallback`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     *
     * @return list<Symbol> The fallback first, then the tokens that fall back to it
     *
     * @throws SyntaxException When something is not a terminal or already falls back
     */
    public function fallbacks(TokenStream $tokens, SymbolRegistry $registry): array
    {
        $symbols = [];
        foreach ($this->untilPeriod($tokens) as $token) {
            if (!$token->isUpperWord()) {
                throw new SyntaxException("%fallback argument \"{$token->raw}\" should be a token", $token->location);
            }
            if ($symbols === []) {
                $registry->see($token->text);
            } else {
                $registry->fallBack($token->text, $token->location);
            }
            $symbols[] = new Symbol($token->text, $token->location);
        }

        return $symbols;
    }

    /**
     * Reads the terminals of `%token`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     * @param string $keyword The keyword, for messages
     *
     * @return list<Symbol> The terminals
     *
     * @throws SyntaxException When something is not a terminal
     */
    public function tokens(TokenStream $tokens, SymbolRegistry $registry, string $keyword): array
    {
        $symbols = [];
        foreach ($this->untilPeriod($tokens) as $token) {
            if (!$token->isUpperWord()) {
                throw new SyntaxException("%{$keyword} argument \"{$token->raw}\" should be a token", $token->location);
            }
            $registry->see($token->text);
            $symbols[] = new Symbol($token->text, $token->location);
        }

        return $symbols;
    }

    /**
     * Reads the terminal of `%wildcard`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     *
     * @return Symbol|null The terminal, or null when none was written
     *
     * @throws SyntaxException When something is not a terminal or a second one is named
     */
    public function wildcard(TokenStream $tokens, SymbolRegistry $registry): ?Symbol
    {
        $symbol = null;
        foreach ($this->untilPeriod($tokens) as $token) {
            if (!$token->isUpperWord()) {
                throw new SyntaxException("%wildcard argument \"{$token->raw}\" should be a token", $token->location);
            }
            $registry->wildcard($token->text, $token->location);
            $symbol = new Symbol($token->text, $token->location);
        }

        return $symbol;
    }

    /**
     * Reads the terminals of `%token_class`, written bare or after `|` or `/`.
     *
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     *
     * @return list<Symbol> The terminals
     *
     * @throws SyntaxException When something is not a terminal
     */
    public function classTokens(TokenStream $tokens, SymbolRegistry $registry): array
    {
        $symbols = [];
        foreach ($this->untilPeriod($tokens) as $token) {
            $compound = $token->is(TokenKind::Compound) && ctype_upper($token->text[0]);
            if (!$token->isUpperWord() && !$compound) {
                throw new SyntaxException("%token_class argument \"{$token->raw}\" should be a token", $token->location);
            }
            $registry->see($token->text);
            $symbols[] = new Symbol($token->text, $token->location);
        }

        return $symbols;
    }

    /**
     * Takes tokens up to the period that ends a list.
     *
     * @param TokenStream $tokens The rest
     *
     * @return list<Token> The tokens before the period
     *
     * @throws SyntaxException When the file ends first
     */
    public function untilPeriod(TokenStream $tokens): array
    {
        $taken = [];
        while (true) {
            $token = $tokens->next();
            if ($token->isPunctuation('.')) {
                return $taken;
            }
            if ($token->is(TokenKind::End)) {
                throw new SyntaxException('Declaration is not terminated by "." before the end of the file.', $token->location);
            }
            $taken[] = $token;
        }
    }
}
