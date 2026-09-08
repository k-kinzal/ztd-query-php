<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Generation\Token;

use Override;

/**
 * Applies a declared, finite sequence of structural rules without interpreting their syntax.
 */
final class TokenRewriter implements RewriteRule
{
    /**
     * @var list<RewriteRule>
     */
    private readonly array $rules;

    /**
     * Retains explicit rule order; no implicit repeated rewriting is performed.
     */
    public function __construct(RewriteRule ...$rules)
    {
        $this->rules = array_values($rules);
    }

    /**
     * Passes each result to the next rule, keeping the original derivation attached.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($this->rules as $rule) {
            $sequence = $rule->rewrite($sequence);
        }
        return $sequence;
    }
}
