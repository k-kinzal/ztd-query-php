<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

/**
 * Implements the Invariant Violation contract for MySQL.
 */
final class InvariantViolation
{
    private string $id;
    private string $description;
    private string $sql;
    /**
     * @var array<string, string|int|bool|null>
     */
    private array $context;

    /**
     * @param array<string, string|int|bool|null> $context
     */
    public function __construct(string $id, string $description, string $sql, array $context = [])
    {
        $this->id = $id;
        $this->description = $description;
        $this->sql = $sql;
        $this->context = $context;
    }

    /**
     * Id for the supplied MySQL input.
     */
    public function id(): string
    {
        return $this->id;
    }

    /**
     * Description for the supplied MySQL input.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Sql for the supplied MySQL input.
     */
    public function sql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<string, string|int|bool|null>
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * __to String for the supplied MySQL input.
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
