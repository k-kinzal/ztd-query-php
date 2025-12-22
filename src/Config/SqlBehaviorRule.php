<?php

declare(strict_types=1);

namespace ZtdQuery\Config;

/**
 * Represents a single SQL behavior rule with pattern and associated behavior.
 */
final class SqlBehaviorRule
{
    /**
     * The pattern to match against SQL statements.
     */
    private string $pattern;

    /**
     * Whether the pattern is a regex (starts with '/').
     */
    private bool $isRegex;

    /**
     * The behavior to apply when the pattern matches.
     */
    private UnsupportedSqlBehavior $behavior;

    /**
     * @param string $pattern The pattern to match (prefix or regex starting with '/').
     * @param UnsupportedSqlBehavior $behavior The behavior to apply when matched.
     */
    public function __construct(string $pattern, UnsupportedSqlBehavior $behavior)
    {
        $this->isRegex = str_starts_with($pattern, '/');
        $this->pattern = $pattern;
        $this->behavior = $behavior;
    }

    /**
     * Check if the given SQL matches this rule.
     */
    public function matches(string $sql): bool
    {
        if ($this->isRegex) {
            return preg_match($this->pattern, $sql) === 1;
        }

        // Prefix matching (case-insensitive)
        $normalizedSql = strtoupper(trim($sql));

        return str_starts_with($normalizedSql, strtoupper($this->pattern));
    }

    /**
     * Get the behavior to apply when this rule matches.
     */
    public function behavior(): UnsupportedSqlBehavior
    {
        return $this->behavior;
    }

    /**
     * Get the pattern.
     */
    public function pattern(): string
    {
        return $this->pattern;
    }

    /**
     * Check if this rule uses regex matching.
     */
    public function isRegex(): bool
    {
        return $this->isRegex;
    }
}
