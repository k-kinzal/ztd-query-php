<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Bison;

use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Grammar\GrammarBuilder;

/**
 * Reads the rules section of a Bison grammar into a builder.
 *
 * An action written before the end of an alternative is a mid-rule action.
 * Bison stands a nonterminal named `$@n` in for it, deriving the empty
 * string, and numbers that nonterminal's rule before the rule it appears in.
 * The same is done here so the automaton and its conflicts come out alike.
 *
 * @visibility root
 */
final class BisonRules
{
    private int $midRuleCount = 0;

    /**
     * Reads rules until the section marker or the end of the tokens.
     *
     * @param BisonTokens $tokens Tokens positioned after the first section marker
     * @param GrammarBuilder $builder Builder to record rules into
     *
     * A stray semicolon between rules is accepted, as Bison accepts it.
     *
     * @throws GrammarSourceException When a rule is not written as `name : alternatives ;`
     */
    public function read(BisonTokens $tokens, GrammarBuilder $builder): void
    {
        while (($token = $tokens->peek()) !== null && $token->kind !== BisonTokenKind::Section) {
            if ($token->kind === BisonTokenKind::Semicolon) {
                $tokens->next();
                continue;
            }
            $lhs = $tokens->take(BisonTokenKind::Identifier, 'a rule name')->text;
            $this->skipNamedReference($tokens);
            $tokens->take(BisonTokenKind::Colon, "':' after rule name '{$lhs}'");
            do {
                $more = $this->alternative($tokens, $builder, $lhs);
            } while ($more);
        }
    }

    /**
     * Reads one alternative and records its rule.
     *
     * @param BisonTokens $tokens Tokens positioned at the start of the alternative
     * @param GrammarBuilder $builder Builder to record the rule into
     * @param string $lhs Nonterminal the alternative derives
     *
     * @return bool True when another alternative of the same rule follows
     *
     * @throws GrammarSourceException When a modifier lacks its argument
     */
    public function alternative(BisonTokens $tokens, GrammarBuilder $builder, string $lhs): bool
    {
        $symbols = [];
        $precedence = null;
        $more = false;
        while (($token = $tokens->peek()) !== null) {
            if ($token->kind === BisonTokenKind::Section || $this->startsRule($tokens)) {
                break;
            }
            $tokens->next();
            if ($token->kind === BisonTokenKind::Semicolon) {
                break;
            }
            if ($token->kind === BisonTokenKind::Pipe) {
                $more = true;
                break;
            }
            if ($token->isSymbol() || $token->kind === BisonTokenKind::String) {
                if ($token->kind !== BisonTokenKind::Identifier) {
                    $builder->terminal($token->text);
                }
                $symbols[] = $token->text;
                $this->skipNamedReference($tokens);
            } elseif ($token->kind === BisonTokenKind::Code && $this->continues($tokens)) {
                $symbols[] = $this->midRule($builder);
            } elseif ($token->isDirective('prec')) {
                $precedence = $tokens->next();
                if ($precedence === null || !$precedence->isSymbol()) {
                    throw GrammarSourceException::unexpected('a symbol after %prec', 'nothing', $token->line);
                }
                $precedence = $precedence->text;
            } elseif ($token->isDirective('dprec')) {
                $tokens->take(BisonTokenKind::Number, 'a number after %dprec');
            } elseif ($token->isDirective('merge')) {
                $tokens->take(BisonTokenKind::Tag, 'a tag after %merge');
            }
        }
        $builder->rule($lhs, $symbols, $precedence);

        return $more;
    }

    /**
     * Records the empty rule that stands in for a mid-rule action.
     *
     * @param GrammarBuilder $builder Builder to record the rule into
     *
     * @return string The synthesised nonterminal's name
     */
    public function midRule(GrammarBuilder $builder): string
    {
        $name = '$@' . ++$this->midRuleCount;
        $builder->rule($name, [], null, true);

        return $name;
    }

    /**
     * Reports whether more of the alternative follows an action.
     *
     * @param BisonTokens $tokens Tokens positioned after the action
     *
     * @return bool True when a symbol or another action follows
     */
    public function continues(BisonTokens $tokens): bool
    {
        $offset = 0;
        while (($token = $tokens->peek($offset)) !== null) {
            if ($token->kind === BisonTokenKind::Tag) {
                $offset++;
                continue;
            }

            return $token->isSymbol()
                || $token->kind === BisonTokenKind::String
                || ($token->kind === BisonTokenKind::Code && !$this->startsRule($tokens, $offset));
        }

        return false;
    }

    /**
     * Reports whether the tokens ahead begin a new rule.
     *
     * A rule begins with a name followed by a colon, optionally with a named
     * reference in between, which is how a rule that omits its semicolon ends.
     *
     * @param BisonTokens $tokens Tokens to look at
     * @param int $offset Distance from the current position to look from
     *
     * @return bool True when a rule starts there
     */
    public function startsRule(BisonTokens $tokens, int $offset = 0): bool
    {
        if ($tokens->peek($offset)?->kind !== BisonTokenKind::Identifier) {
            return false;
        }
        $following = $tokens->peek($offset + 1);
        if ($following?->kind === BisonTokenKind::BracketOpen) {
            $following = $tokens->peek($offset + 4);
        }

        return $following?->kind === BisonTokenKind::Colon;
    }

    /**
     * Skips a `[name]` reference attached to the preceding symbol.
     *
     * @param BisonTokens $tokens Tokens positioned after the symbol
     *
     * @throws GrammarSourceException When the reference is not closed
     */
    public function skipNamedReference(BisonTokens $tokens): void
    {
        if ($tokens->peek()?->kind !== BisonTokenKind::BracketOpen) {
            return;
        }
        $tokens->next();
        $tokens->take(BisonTokenKind::Identifier, 'a name inside brackets');
        $tokens->take(BisonTokenKind::BracketClose, "']' after a named reference");
    }
}
