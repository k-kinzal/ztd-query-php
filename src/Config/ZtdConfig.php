<?php

declare(strict_types=1);

namespace ZtdQuery\Config;

/**
 * Configuration for ZTD behavior.
 */
final class ZtdConfig
{
    /**
     * How to handle unsupported SQL statements.
     */
    private UnsupportedSqlBehavior $unsupportedBehavior;

    /**
     * How to handle queries referencing unknown tables/columns.
     */
    private UnknownSchemaBehavior $unknownSchemaBehavior;

    /**
     * Ordered list of behavior rules.
     *
     * @var list<SqlBehaviorRule>
     */
    private array $behaviorRules;

    /**
     * @param UnsupportedSqlBehavior $unsupportedBehavior How to handle unsupported SQL.
     * @param UnknownSchemaBehavior $unknownSchemaBehavior How to handle unknown schema references.
     * @param array<string, UnsupportedSqlBehavior> $behaviorRules Pattern-to-behavior mapping (first match wins).
     */
    public function __construct(
        UnsupportedSqlBehavior $unsupportedBehavior = UnsupportedSqlBehavior::Exception,
        UnknownSchemaBehavior $unknownSchemaBehavior = UnknownSchemaBehavior::Passthrough,
        array $behaviorRules = []
    ) {
        $this->unsupportedBehavior = $unsupportedBehavior;
        $this->unknownSchemaBehavior = $unknownSchemaBehavior;
        $this->behaviorRules = [];

        foreach ($behaviorRules as $pattern => $behavior) {
            $this->behaviorRules[] = new SqlBehaviorRule($pattern, $behavior);
        }
    }

    /**
     * Create a default configuration with Exception behavior.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Get the default unsupported SQL behavior mode.
     */
    public function unsupportedBehavior(): UnsupportedSqlBehavior
    {
        return $this->unsupportedBehavior;
    }

    /**
     * Get the unknown schema behavior mode.
     */
    public function unknownSchemaBehavior(): UnknownSchemaBehavior
    {
        return $this->unknownSchemaBehavior;
    }

    /**
     * Get the behavior rules.
     *
     * @return list<SqlBehaviorRule>
     */
    public function behaviorRules(): array
    {
        return $this->behaviorRules;
    }

    /**
     * Get the behavior for a specific SQL statement.
     *
     * Checks behavior rules in order; first matching rule determines behavior.
     * Returns null if no rule matches (use default unsupportedBehavior).
     */
    public function getBehaviorFor(string $sql): ?UnsupportedSqlBehavior
    {
        foreach ($this->behaviorRules as $rule) {
            if ($rule->matches($sql)) {
                return $rule->behavior();
            }
        }

        return null;
    }

    /**
     * Resolve the effective behavior for a SQL statement.
     *
     * Uses rule-specific behavior if a rule matches, otherwise default behavior.
     */
    public function resolveUnsupportedBehavior(string $sql): UnsupportedSqlBehavior
    {
        return $this->getBehaviorFor($sql) ?? $this->unsupportedBehavior;
    }
}
