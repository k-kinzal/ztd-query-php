<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_stmt;
use Override;

/**
 * Isolates native mysqli by-reference signatures that PHPStan models incorrectly.
 */
abstract class MysqliStatementBindingBridge extends mysqli_stmt
{
    private mysqli_stmt $bindingDelegate;

    /**
     * Retain the native statement used for reference binding.
     */
    public function __construct(mysqli_stmt $bindingDelegate)
    {
        $this->bindingDelegate = $bindingDelegate;
    }

    /**
     * @param mixed ...$vars
     */
    #[Override]
    final public function bind_param(string $types, mixed &...$vars): bool
    {
        return $this->bindingDelegate->bind_param($types, ...$vars);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    final public function bind_result(mixed &...$vars): bool
    {
        return $this->bindingDelegate->bind_result(...$vars);
    }
}
