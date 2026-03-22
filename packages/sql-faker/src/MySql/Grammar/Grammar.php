<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use InvalidArgumentException;

/**
 * Represents a formal grammar for SQL generation.
 *
 * A formal grammar G = (N, Σ, P, S) where:
 * - N: Set of non-terminal symbols (keys of ruleMap)
 * - Σ: Set of terminal symbols (implicitly defined by Symbol types)
 * - P: Set of production rules (ruleMap)
 * - S: Start symbol (startSymbol)
 */
final class Grammar
{
    /**
     * @param string $startSymbol The grammar's start symbol
     * @param array<string, ProductionRule> $ruleMap Non-terminal name => ProductionRule
     */
    public function __construct(
        public readonly string $startSymbol,
        public readonly array $ruleMap,
    ) {
        foreach ($ruleMap as $key => $rule) {
            if ($key !== $rule->lhs) {
                throw new InvalidArgumentException(
                    "Rule key '{$key}' does not match rule lhs '{$rule->lhs}'"
                );
            }
        }
    }
}
