<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;

/**
 * The global values of the system variables of one server.
 *
 * A variable never set globally holds the default of its definition, or the value the server
 * was started with. A few text variables hold NULL.
 *
 * @visibility MySqlMemory
 */
final class Globals
{
    /**
     * @param array<string, string|int|null> $values The values set at start or by SET GLOBAL, by lower-case name
     */
    public function __construct(public array $values = [])
    {
    }

    /**
     * Answers the global value of a variable.
     */
    public function value(Definition $definition): string|int|null
    {
        return array_key_exists($definition->name, $this->values) ? $this->values[$definition->name] : $definition->default;
    }

    /**
     * Sets the global value of a variable.
     */
    public function set(Definition $definition, string|int|null $value): void
    {
        $this->values[$definition->name] = $value;
    }
}
