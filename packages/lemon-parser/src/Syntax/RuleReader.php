<?php

declare(strict_types=1);

namespace LemonParser\Syntax;

use LemonParser\Ast\RhsItem;
use LemonParser\Ast\Rule;
use LemonParser\Ast\Symbol;
use LemonParser\Scanner\Token;
use LemonParser\Scanner\TokenKind;
use LemonParser\SyntaxException;

/**
 * Reads a rule from its left-hand side to its period, as Lemon's rule states do.
 *
 * @visibility root
 */
final class RuleReader
{
    /**
     * Reads a rule whose left-hand side has been taken.
     *
     * @param Token $lhs The nonterminal token
     * @param TokenStream $tokens The rest
     * @param SymbolRegistry $registry Symbols seen so far
     *
     * @return Rule The rule, without precedence mark or code
     *
     * @throws SyntaxException Where Lemon reports an error
     */
    public function read(Token $lhs, TokenStream $tokens, SymbolRegistry $registry): Rule
    {
        $registry->see($lhs->text);
        $alias = $this->head($lhs, $tokens);
        $items = [];
        while (true) {
            $token = $tokens->next();
            if ($token->isPunctuation('.')) {
                break;
            }
            if ($token->isAlphaWord()) {
                $registry->see($token->text);
                $items[] = new RhsItem([new Symbol($token->text, $token->location)], null);
            } elseif ($token->is(TokenKind::Compound) && $items !== [] && ctype_upper($token->text[0])) {
                $registry->see($token->text);
                $items[count($items) - 1] = $this->compound($items[count($items) - 1], $token);
            } elseif ($token->isPunctuation('(') && $items !== []) {
                $items[count($items) - 1] = $this->alias($items[count($items) - 1], $tokens, $lhs, $alias);
            } elseif ($token->is(TokenKind::End)) {
                throw new SyntaxException("Rule \"{$lhs->text}\" is not terminated by \".\" before the end of the file.", $lhs->location);
            } else {
                throw new SyntaxException("Illegal character on RHS of rule: \"{$token->raw}\".", $token->location);
            }
        }

        return new Rule(new Symbol($lhs->text, $lhs->location), $alias, $items, null, null, false, $lhs->location);
    }

    /**
     * Reads what follows the left-hand side up to and including `::=`.
     *
     * @param Token $lhs The nonterminal token
     * @param TokenStream $tokens The rest
     *
     * @return string|null The alias of the left-hand side, or null
     *
     * @throws SyntaxException Where Lemon reports an error
     */
    public function head(Token $lhs, TokenStream $tokens): ?string
    {
        $token = $tokens->next();
        if ($token->is(TokenKind::Arrow)) {
            return null;
        }
        if (!$token->isPunctuation('(')) {
            throw new SyntaxException("Expected to see a \":\" following the LHS symbol \"{$lhs->text}\".", $token->location);
        }
        $name = $tokens->next();
        if (!$name->isAlphaWord()) {
            throw new SyntaxException("\"{$name->raw}\" is not a valid alias for the LHS \"{$lhs->text}\"", $name->location);
        }
        $close = $tokens->next();
        if (!$close->isPunctuation(')')) {
            throw new SyntaxException("Missing \")\" following LHS alias name \"{$name->text}\".", $close->location);
        }
        $arrow = $tokens->next();
        if (!$arrow->is(TokenKind::Arrow)) {
            throw new SyntaxException("Missing \"->\" following: \"{$lhs->text}({$name->text})\".", $arrow->location);
        }

        return $name->text;
    }

    /**
     * Adds a terminal to the position before it, making a multi-terminal.
     *
     * @param RhsItem $item The position
     * @param Token $token The `|X` or `/X` token
     *
     * @return RhsItem The position with the terminal added
     *
     * @throws SyntaxException When the position holds a nonterminal
     */
    public function compound(RhsItem $item, Token $token): RhsItem
    {
        if ($item->symbols[0]->isNonterminal()) {
            throw new SyntaxException('Cannot form a compound containing a non-terminal', $token->location);
        }

        return new RhsItem([...$item->symbols, new Symbol($token->text, $token->location)], $item->alias);
    }

    /**
     * Reads `name)` after an opening parenthesis and names the position before it.
     *
     * @param RhsItem $item The position
     * @param TokenStream $tokens The rest
     * @param Token $lhs The rule's nonterminal, for messages
     * @param string|null $lhsAlias The rule's alias, for messages
     *
     * @return RhsItem The named position
     *
     * @throws SyntaxException Where Lemon reports an error
     */
    public function alias(RhsItem $item, TokenStream $tokens, Token $lhs, ?string $lhsAlias): RhsItem
    {
        $name = $tokens->next();
        if (!$name->isAlphaWord()) {
            throw new SyntaxException("\"{$name->raw}\" is not a valid alias for the RHS symbol \"{$item->symbols[0]->name}\"", $name->location);
        }
        $close = $tokens->next();
        if (!$close->isPunctuation(')')) {
            throw new SyntaxException("Missing \")\" following LHS alias name \"{$lhsAlias}\".", $close->location);
        }

        return new RhsItem($item->symbols, $name->text);
    }
}
