<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\Fill;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Storage\Store;
use MySqlMemory\Typing\Domain;

/**
 * A parameter or local variable of a running stored program, or a column of the NEW or OLD row of a trigger: its name, its declared type and its value.
 *
 * @visibility MySqlMemory
 */
final class Variable
{
    /**
     * @param string $name The name as declared
     * @param Domain $domain The declared type
     * @param int|float|string|null $value The value it holds
     */
    public function __construct(public readonly string $name, public readonly Domain $domain, public int|float|string|null $value = null)
    {
    }

    /**
     * Stores a value as into a column of the declared type, named after the variable in the warnings and errors it raises.
     *
     * @throws SqlError When the value is refused under a strict sql_mode
     */
    public function assign(int|float|string|null $value, Domain $from, Context $context): void
    {
        $this->value = (new Store($context))->value($value, $from, new ColumnDefinition($this->name, $this->domain, Fill::none()));
    }
}
