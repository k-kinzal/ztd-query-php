<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Grammar;

use InvalidArgumentException;
use RuntimeException;
use SqlFaker\Grammar\Grammar as CommonGrammar;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\SqlVersion;

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
     *
     * @throws InvalidArgumentException When a rule is filed under a name other than its own left-hand side
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
     *
     * @throws RuntimeException When the compiled grammar is missing, empty, or is not a grammar
     */
    public static function load(?string $version = null): self
    {
        $path = SqlVersion::resolve('mysql', $version)->astPath;

        $grammar = CommonGrammar::loadFromFile($path);

        return new self($grammar->startSymbol, $grammar->ruleMap);
    }

    /**
     * Answers the release a version string names, defaulting to the newest one shipped.
     *
     * @param string|null $version Release to resolve, or null for the default
     *
     * @return string Name of the release the artifacts were generated for
     *
     * @throws RuntimeException When the release is not one this package ships
     */
    public static function resolveVersion(?string $version = null): string
    {
        return SqlVersion::resolve('mysql', $version)->name;
    }

    /**
     * Resolves the requested MySQL rule name.
     *
     * @param string|null $requested Requested rule or the default
     * @return string Rule name used by this grammar release
     */
    public function startSymbolFor(?string $requested): string
    {
        return (new \SqlFaker\MySql\StartRuleResolver(new CommonGrammar($this->startSymbol, $this->ruleMap)))
            ->startSymbolFor($requested);
    }
}
