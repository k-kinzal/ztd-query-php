<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

use SqlParser\Compiler\GrammarSourceException;
use SqlParser\Grammar\Associativity;
use SqlParser\Grammar\GrammarBuilder;

/**
 * Reads one Lemon directive into a builder.
 *
 * Tokens, precedence groups, fallbacks, the wildcard, token classes and the
 * start symbol shape the table and are recorded. The directives that carry
 * host code or parser settings are skipped with their arguments.
 *
 * @visibility root
 */
final class LemonDirectives
{
    /**
     * Directives followed by one code block.
     */
    public const CODE = ['include', 'code', 'token_type', 'default_type', 'extra_argument', 'extra_context', 'syntax_error', 'stack_overflow', 'parse_accept', 'parse_failure', 'token_destructor', 'default_destructor'];

    /**
     * Directives followed by one symbol and then one code block.
     */
    public const SYMBOL_CODE = ['type', 'destructor'];

    /**
     * Reads the directive whose name token was just consumed.
     *
     * @param string $name Directive name without the percent sign
     * @param LemonTokens $tokens Tokens positioned after the name
     * @param GrammarBuilder $builder Builder to declare into
     *
     * @throws GrammarSourceException When the directive lacks its argument
     */
    public function read(string $name, LemonTokens $tokens, GrammarBuilder $builder): void
    {
        $associativity = match ($name) {
            'left' => Associativity::Left,
            'right' => Associativity::Right,
            'nonassoc' => Associativity::NonAssoc,
            default => null,
        };
        if ($associativity !== null) {
            $builder->precedence($tokens->namesUntilDot(), $associativity);
        } elseif ($name === 'token') {
            foreach ($tokens->namesUntilDot() as $terminal) {
                $builder->terminal($terminal);
            }
        } elseif ($name === 'fallback') {
            $names = $tokens->namesUntilDot();
            $target = array_shift($names);
            if ($target === null) {
                throw GrammarSourceException::unexpected('a fallback token', "'.'", $tokens->peek(-1)->line ?? 1);
            }
            $builder->fallback($target, $names);
        } elseif ($name === 'wildcard') {
            $builder->wildcard($tokens->take(LemonTokenKind::Identifier, 'a wildcard token')->text);
            $tokens->take(LemonTokenKind::Dot, "'.' after the wildcard token");
        } elseif ($name === 'token_class') {
            $class = $tokens->take(LemonTokenKind::Identifier, 'a token class name')->text;
            $builder->tokenClass($class, $tokens->namesUntilDot());
        } elseif ($name === 'start_symbol') {
            $builder->start($tokens->take(LemonTokenKind::Identifier, 'a start symbol')->text);
        } else {
            $this->skip($name, $tokens);
        }
    }

    /**
     * Skips the arguments of a directive that does not shape the table.
     *
     * @param string $name Directive name without the percent sign
     * @param LemonTokens $tokens Tokens positioned after the name
     *
     * @throws GrammarSourceException When the directive lacks its code block
     */
    public function skip(string $name, LemonTokens $tokens): void
    {
        if (in_array($name, self::SYMBOL_CODE, true)) {
            $tokens->take(LemonTokenKind::Identifier, "a symbol after %{$name}");
            $tokens->take(LemonTokenKind::Code, "a code block after %{$name}");
        } elseif (in_array($name, self::CODE, true)) {
            $tokens->take(LemonTokenKind::Code, "a code block after %{$name}");
        } elseif (!$tokens->atEnd()) {
            $tokens->next();
        }
    }
}
