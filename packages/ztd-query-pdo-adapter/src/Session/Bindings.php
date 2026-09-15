<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use Closure;
use PDOStatement;

/**
 * Replays native bindings and fetch options whenever rewriting replaces a statement.
 * Callbacks retain references and arbitrary native driver arguments at the PDO boundary.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class Bindings
{
    /** @var array<int|string, Closure(PDOStatement): bool> */
    private array $parameters = [];

    /** @var (Closure(PDOStatement): bool)|null */
    private ?Closure $fetch = null;

    /**
     * Remember the latest binding made for this placeholder.
     *
     * @param Closure(PDOStatement): bool $binding Native bindValue or bindParam operation.
     */
    public function parameter(int|string $name, Closure $binding): void
    {
        $this->parameters[$name] = $binding;
    }

    /**
     * Remember the caller's native fetch options.
     *
     * @param Closure(PDOStatement): bool $fetch Native setFetchMode operation.
     */
    public function fetch(Closure $fetch): void
    {
        $this->fetch = $fetch;
    }

    /**
     * Apply the remembered options to a newly prepared native statement.
     */
    public function apply(PDOStatement $statement): void
    {
        foreach ($this->parameters as $binding) {
            $binding($statement);
        }
        if ($this->fetch !== null) {
            ($this->fetch)($statement);
        }
    }
}
