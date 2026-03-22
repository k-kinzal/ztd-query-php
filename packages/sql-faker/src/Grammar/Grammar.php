<?php

declare(strict_types=1);

namespace SqlFaker\Grammar;

use InvalidArgumentException;
use RuntimeException;

/**
 * Represents a formal grammar for SQL generation.
 *
 * A formal grammar G = (N, Sigma, P, S) where:
 * - N: Set of non-terminal symbols (keys of ruleMap)
 * - Sigma: Set of terminal symbols (implicitly defined by Symbol types)
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

    /**
     * Load a pre-compiled grammar from a file path.
     */
    public static function loadFromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new RuntimeException("Grammar file not found: {$path}");
        }

        /** @var array<string, string> $data */
        $data = require $path;
        $hash = array_key_first($data);
        if ($hash === null) {
            throw new RuntimeException("Invalid grammar file: {$path}");
        }
        $serialized = $data[$hash] ?? null;
        if (!is_string($serialized) || $serialized === '') {
            throw new RuntimeException("Invalid grammar file: {$path}");
        }

        $grammar = unserialize($serialized);
        if ($grammar instanceof self) {
            return $grammar;
        }

        throw new RuntimeException("Failed to load grammar from: {$path}");
    }
}
