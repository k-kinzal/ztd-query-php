<?php

declare(strict_types=1);

namespace SqlCatalog\Core\Catalog;

/**
 * One thing worth reporting about a catalogued statement.
 *
 * @visibility root
 */
final class Finding
{
    /**
     * @param FindingRule $rule What the finding is about
     * @param Severity $severity How much attention it deserves
     * @param string $message What to tell the reader
     */
    public function __construct(
        public readonly FindingRule $rule,
        public readonly Severity $severity,
        public readonly string $message,
    ) {
    }

    /**
     * A finding at the rule's own severity.
     */
    public static function of(FindingRule $rule, string $message): self
    {
        return new self($rule, $rule->severity(), $message);
    }
}
