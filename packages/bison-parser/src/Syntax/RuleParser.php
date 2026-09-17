<?php

declare(strict_types=1);

namespace BisonParser\Syntax;

use BisonParser\Ast\Location;
use BisonParser\Ast\Rule\Action;
use BisonParser\Ast\Rule\Alternative;
use BisonParser\Ast\Rule\DprecItem;
use BisonParser\Ast\Rule\EmptyItem;
use BisonParser\Ast\Rule\ExpectItem;
use BisonParser\Ast\Rule\MergeItem;
use BisonParser\Ast\Rule\PrecItem;
use BisonParser\Ast\Rule\Predicate;
use BisonParser\Ast\Rule\RhsItem;
use BisonParser\Ast\Rule\Rule;
use BisonParser\Ast\Rule\SymbolItem;
use BisonParser\Scanner\Token;
use BisonParser\Scanner\TokenKind;
use BisonParser\SyntaxException;

/**
 * Reads one rule, following the `rules` and `rhs` rules of `parse-gram.y`.
 *
 * An alternative ends at `|`, at `;`, or where the next rule or a
 * declaration begins, which is how Bison reads a rule that omits its
 * semicolon. Extra semicolons after a rule are accepted as Bison accepts them.
 *
 * @visibility root
 */
final class RuleParser
{
    /**
     * @param SymbolListParser $symbols Turns symbol tokens into symbols
     * @param DeclarationParser $lines Turns `#line` tokens into nodes
     */
    public function __construct(
        private readonly SymbolListParser $symbols = new SymbolListParser(),
        private readonly DeclarationParser $lines = new DeclarationParser(),
    ) {
    }

    /**
     * Reads the rule that begins at the current token.
     *
     * @param TokenStream $tokens Tokens positioned on the rule's left-hand side
     *
     * @return Rule The rule
     *
     * @throws SyntaxException When the rule is not written as `name: alternatives`
     */
    public function parse(TokenStream $tokens): Rule
    {
        $lhs = $tokens->expect(TokenKind::IdentifierColon, 'a rule');
        $reference = $tokens->accept(TokenKind::BracketedIdentifier)?->text;
        $tokens->expect(TokenKind::Colon, "':' after '{$lhs->text}'");
        $alternatives = [$this->alternative($tokens, $tokens->peek()->location)];
        while (true) {
            $pipe = $tokens->accept(TokenKind::Pipe);
            if ($pipe !== null) {
                $alternatives[] = $this->alternative($tokens, $pipe->location);
            } elseif ($tokens->accept(TokenKind::Semicolon) === null) {
                break;
            }
        }

        return new Rule($this->symbols->symbol($lhs), $reference, $alternatives, $lhs->location);
    }

    /**
     * Reads the items of one alternative.
     *
     * @param TokenStream $tokens Tokens positioned on the first item, or on what ends the alternative
     * @param Location $at Where the alternative begins
     *
     * @return Alternative The alternative
     *
     * @throws SyntaxException When an item is malformed
     */
    public function alternative(TokenStream $tokens, Location $at): Alternative
    {
        $items = [];
        while (($item = $this->item($tokens)) !== null) {
            $items[] = $item;
        }

        return new Alternative($items, $items === [] ? $at : $items[0]->location());
    }

    /**
     * Reads one item of a right-hand side.
     *
     * @param TokenStream $tokens Tokens positioned on the item
     *
     * @return RhsItem|null The item, or null when the alternative ends here
     *
     * @throws SyntaxException When the item is malformed
     */
    public function item(TokenStream $tokens): ?RhsItem
    {
        $token = $tokens->peek();
        if ($this->symbols->startsSymbol($token)) {
            $tokens->next();

            return new SymbolItem($this->symbols->symbol($token), $tokens->accept(TokenKind::BracketedIdentifier)?->text);
        }
        if ($token->is(TokenKind::Tag) || $token->is(TokenKind::Code)) {
            return $this->action($tokens);
        }
        if ($token->is(TokenKind::Predicate)) {
            $tokens->next();

            return new Predicate($token->text, $token->location);
        }
        if ($token->is(TokenKind::Line)) {
            $tokens->next();

            return $this->lines->line($token);
        }
        if (!$token->is(TokenKind::Directive)) {
            return null;
        }

        return $this->modifier($tokens);
    }

    /**
     * Reads a braced action with its optional tag and name.
     *
     * @param TokenStream $tokens Tokens positioned on the tag or the code
     *
     * @return Action The action
     *
     * @throws SyntaxException When a tag is not followed by braced code
     */
    public function action(TokenStream $tokens): Action
    {
        $first = $tokens->next();
        $tag = null;
        $code = $first;
        if ($first->is(TokenKind::Tag)) {
            $tag = $first->text;
            $code = $tokens->expect(TokenKind::Code, 'braced code after the tag');
        }

        return new Action($tag, $code->text, $tokens->accept(TokenKind::BracketedIdentifier)?->text, $first->location);
    }

    /**
     * Reads a `%`-modifier of a right-hand side.
     *
     * @param TokenStream $tokens Tokens positioned on the directive
     *
     * @return RhsItem|null The modifier, or null when the directive begins a declaration instead
     *
     * @throws SyntaxException When the modifier lacks its argument
     */
    public function modifier(TokenStream $tokens): ?RhsItem
    {
        $token = $tokens->peek();
        $name = $token->text;
        if (!in_array($name, ['empty', 'prec', 'dprec', 'merge', 'expect', 'expect-rr'], true)) {
            return null;
        }
        $tokens->next();

        return match ($name) {
            'empty' => new EmptyItem($token->location),
            'prec' => new PrecItem($this->symbols->symbol($this->symbolToken($tokens)), $token->location),
            'dprec' => new DprecItem((int) $tokens->expect(TokenKind::Integer, 'a number after %dprec')->text, $token->location),
            'merge' => new MergeItem($tokens->expect(TokenKind::Tag, 'a tag after %merge')->text, $token->location),
            'expect', 'expect-rr' => new ExpectItem((int) $tokens->expect(TokenKind::Integer, "a number after %{$name}")->text, $name === 'expect-rr', $token->location),
        };
    }

    /**
     * Consumes the symbol token that must follow `%prec`.
     *
     * @param TokenStream $tokens Tokens positioned after `%prec`
     *
     * @return Token The symbol token
     *
     * @throws SyntaxException When no symbol follows
     */
    public function symbolToken(TokenStream $tokens): Token
    {
        $token = $tokens->peek();
        if (!$this->symbols->startsSymbol($token)) {
            throw SyntaxException::unexpected('a symbol after %prec', $token->describe(), $token->location);
        }

        return $tokens->next();
    }
}
