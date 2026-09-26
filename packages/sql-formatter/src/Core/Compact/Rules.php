<?php

declare(strict_types=1);

namespace SqlFormatter\Core\Compact;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;

/**
 * Recognizes optional syntax by its grammar owner rather than neighboring words.
 *
 * @visibility SqlFormatter
 */
final class Rules
{
    /**
     * @param array<string, list<list<string>>> $defaults Optional spellings by grammar rule
     * @param list<string> $joins Grammar rules that own join modifiers
     * @param array<string, string> $distinctParents Distinct quantifiers eligible under a parent
     */
    public function __construct(
        private readonly array $defaults,
        private readonly array $joins,
        private readonly array $distinctParents,
        private readonly ?string $selectOptions = null,
        private readonly ?string $strictJoin = null,
    ) {
    }

    /**
     * Removes defaults only from the rules in which omission has the same meaning.
     *
     * @param list<Token> $tokens
     * @return list<Token>
     */
    public function apply(Node $node, array $tokens, ?Node $parent): array
    {
        $words = array_map(static fn (Token $token): string => strtoupper($token->text), $tokens);
        if ($this->default($node->name, $words, $parent)) {
            return [];
        }
        if ($node->name === $this->selectOptions && !in_array('DISTINCT', $words, true)) {
            return array_values(array_filter($tokens, static fn (Token $token): bool => $token->name !== 'ALL'));
        }
        if (in_array($node->name, $this->joins, true)) {
            if ($node->name !== $this->strictJoin || preg_match('/^(?:NATURAL )?(?:INNER|(?:LEFT|RIGHT|FULL)(?: OUTER)?) JOIN$/D', implode(' ', $words)) === 1) {
                return array_values(array_filter($tokens, static fn (Token $token): bool => !in_array(strtoupper($token->text), ['INNER', 'OUTER'], true)));
            }
        }
        return $tokens;
    }

    /**
     * @param list<string> $words
     */
    public function default(string $rule, array $words, ?Node $parent): bool
    {
        return in_array($words, $this->defaults[$rule] ?? [], true)
            || ($words === ['DISTINCT'] && isset($this->distinctParents[$rule]) && $parent?->name === $this->distinctParents[$rule]);
    }
}
