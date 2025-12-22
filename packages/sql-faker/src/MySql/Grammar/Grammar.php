<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use InvalidArgumentException;
use RuntimeException;

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
    private const AST_DIR = __DIR__ . '/../../ast';
    private const AST_META = __DIR__ . '/../../ast.php';

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
     * Load a pre-compiled grammar.
     *
     * @param string|null $version MySQL version tag (e.g., "mysql-8.4.0"). Null for default.
     */
    public static function load(?string $version = null): self
    {
        if ($version === null) {
            /** @var array{default: string} $meta */
            $meta = require self::AST_META;
            $version = $meta['default'];
        }

        $path = self::AST_DIR . '/' . $version . '.php';

        if (!file_exists($path)) {
            throw new RuntimeException("Grammar file not found: {$path}");
        }

        /** @var array<string, string> $data */
        $data = require $path;
        $hash = array_key_first($data);
        if ($hash === null) {
            throw new RuntimeException("Invalid grammar file: {$path}");
        }
        $grammar = unserialize($data[$hash]);

        if (!$grammar instanceof self) {
            throw new RuntimeException("Failed to load grammar from: {$path}");
        }

        return $grammar;
    }
}
