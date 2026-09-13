<?php

declare (strict_types=1);

namespace Fuzz\Robustness\Invariant;

/**
 * Invariant violation for PostgreSQL queries.
 */
final class InvariantViolation
{
    private string $id;
    private string $description;
    private string $sql;
    /**
     * @var array<string, string|int>
     */
    private array $context;
    /**
     * @param array<string, string|int> $context
     */
    public function __construct(string $id, string $description, string $sql, array $context = [])
    {
        $this->id = $id;
        $this->description = $description;
        $this->sql = $sql;
        $this->context = $context;
    }
    /**
     * Id.
     */
    public function id(): string
    {
        return $this->id;
    }
    /**
     * Description.
     */
    public function description(): string
    {
        return $this->description;
    }
    /**
     * Sql.
     */
    public function sql(): string
    {
        return $this->sql;
    }
    /**
     * @return array<string, string|int>
     */
    public function context(): array
    {
        return $this->context;
    }
    /**
     * __to string.
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
