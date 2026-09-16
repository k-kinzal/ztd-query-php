<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Grammar\GrammarBuilder;

/**
 * Reads one Lemon rule into a builder.
 *
 * Lemon spells a terminal in capitals and a nonterminal in lower case, so a
 * name declares its own kind. A position written as `A|B` matches either
 * terminal; it is recorded as an anonymous token class, which is how Lemon
 * itself treats it.
 *
 * @visibility root
 */
final class LemonRules
{
    /**
     * Reads the rule that starts at the current token.
     *
     * @param LemonTokens $tokens Tokens positioned on the rule's left-hand side
     * @param GrammarBuilder $builder Builder to record the rule into
     *
     * @throws GrammarSourceException When the rule is not written as `lhs ::= symbols .`
     */
    public function read(LemonTokens $tokens, GrammarBuilder $builder): void
    {
        $lhs = $tokens->take(LemonTokenKind::Identifier, 'a rule name')->text;
        $this->skipAlias($tokens);
        $tokens->take(LemonTokenKind::Arrow, "'::=' after rule name '{$lhs}'");
        $symbols = [];
        while (true) {
            $token = $tokens->next();
            if ($token === null) {
                throw GrammarSourceException::unterminated("rule '{$lhs}'", 1);
            }
            if ($token->is(LemonTokenKind::Dot)) {
                break;
            }
            if (!$token->is(LemonTokenKind::Identifier)) {
                throw GrammarSourceException::unexpected("a symbol in rule '{$lhs}'", "'{$token->text}'", $token->line);
            }
            $symbols[] = $this->symbol($token->text, $tokens, $builder);
            $this->skipAlias($tokens);
        }
        $precedence = null;
        if ($tokens->peek()?->is(LemonTokenKind::PrecedenceMark) === true) {
            $precedence = $tokens->next()?->text;
        }
        if ($tokens->peek()?->is(LemonTokenKind::Code) === true) {
            $tokens->next();
        }
        $builder->rule($lhs, $symbols, $precedence);
    }

    /**
     * Records one right-hand symbol, gathering an alternation into a token class.
     *
     * @param string $name Name of the symbol just read
     * @param LemonTokens $tokens Tokens positioned after the name
     * @param GrammarBuilder $builder Builder to declare terminals into
     *
     * @return string The symbol name to put in the rule
     *
     * @throws GrammarSourceException When an alternation joins a nonterminal
     */
    public function symbol(string $name, LemonTokens $tokens, GrammarBuilder $builder): string
    {
        $members = [$name];
        while ($tokens->peek()?->is(LemonTokenKind::Pipe) === true) {
            $tokens->next();
            $members[] = $tokens->take(LemonTokenKind::Identifier, "a terminal after '|'")->text;
        }
        if (count($members) === 1) {
            if (self::isTerminalName($name) && !$builder->isTerminal($name)) {
                $builder->terminal($name);
            }

            return $name;
        }
        foreach ($members as $member) {
            if (!self::isTerminalName($member)) {
                throw GrammarSourceException::unexpected('a terminal in an alternation', "'{$member}'", $tokens->peek(-1)->line ?? 1);
            }
        }
        $class = implode('|', $members);
        if (!$builder->isTerminal($class)) {
            $builder->tokenClass($class, $members);
        }

        return $class;
    }

    /**
     * Skips the alias in parentheses that may follow a symbol.
     *
     * @param LemonTokens $tokens Tokens positioned after the symbol
     */
    public function skipAlias(LemonTokens $tokens): void
    {
        if ($tokens->peek()?->is(LemonTokenKind::Alias) === true) {
            $tokens->next();
        }
    }

    /**
     * Reports whether a name is spelled the way Lemon spells a terminal.
     *
     * @param string $name Symbol name
     *
     * @return bool True when the first letter is a capital
     */
    public static function isTerminalName(string $name): bool
    {
        return preg_match('/^[A-Z]/', $name) === 1;
    }
}
