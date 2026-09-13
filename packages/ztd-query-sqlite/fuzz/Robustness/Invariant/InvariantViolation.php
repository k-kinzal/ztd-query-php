<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

/**
 * Carries the invariant identifier, SQL and reproducible diagnostic context.
 */
final class InvariantViolation
{
    private string $id;
    private string $description;
    private string $sql;
    /** @var array<string, string> */
    private array $context;

    /**
     * @param array<string, string> $context
     */
    public function __construct(string $id, string $description, string $sql, array $context = [])
    {
        $this->id = $id;
        $this->description = $description;
        $this->sql = $sql;
        $this->context = $context;
    }

    /**
     * Returns the stable invariant identifier.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Returns the violated contract description.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Returns the exact SQL that triggered the finding.
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<string, string>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * Formats the finding for crash replay diagnostics.
     */
    public function __toString(): string
    {
        $msg = sprintf("[%s] %s\nSQL: %s", $this->id, $this->description, $this->sql);
        if ($this->context !== []) {
            $msg .= "\nContext: " . json_encode($this->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        return $msg;
    }
}
