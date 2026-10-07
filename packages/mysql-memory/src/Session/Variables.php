<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Scope;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

/**
 * The variables of a session: its user variables, and the session values of the system variables.
 *
 * A user variable holds a value with the domain of what was assigned; one never assigned is
 * NULL. A system variable without a session value of its own reads the global value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html.
 *
 * @visibility MySqlMemory
 */
final class Variables
{
    /**
     * @var array<string, array{int|float|string|null, Domain}> The value and domain of each user variable, by lower-case name
     */
    public array $user = [];

    /**
     * @var array<string, string|int> The session values, by lower-case name
     */
    public array $session = [];

    /**
     * The current database, or the empty string for none.
     */
    public string $database = '';

    /**
     * The account the connection authenticated as, `user@host` (USER()).
     */
    public string $account = 'root@localhost';

    /**
     * The account the privileges are checked for (CURRENT_USER()).
     */
    public string $definer = 'root@%';

    /**
     * The connection id (CONNECTION_ID()).
     */
    public int $connection = 1;

    /**
     * The first AUTO_INCREMENT value the last insert generated (LAST_INSERT_ID()), held as an unsigned int.
     */
    public int $lastInsertId = 0;

    /**
     * Whether LAST_INSERT_ID(n) set the value in the current statement.
     */
    public bool $setByFunction = false;

    /**
     * The rows the last statement changed (ROW_COUNT()).
     */
    public int $rowCount = -1;

    /**
     * The rows the last SELECT found (FOUND_ROWS()).
     */
    public int $foundRows = 0;

    /**
     * @param SystemVariables $catalog The system variables the server knows
     * @param Globals $globals The global values of the server
     */
    public function __construct(public readonly SystemVariables $catalog, public readonly Globals $globals)
    {
    }

    /**
     * Answers the value and domain of a user variable.
     *
     * @return array{int|float|string|null, Domain}
     */
    public function user(string $name): array
    {
        return $this->user[strtolower($name)] ?? [null, Domain::string(0, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary(), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::MediumBlob)];
    }

    /**
     * Assigns a user variable.
     */
    public function assign(string $name, int|float|string|null $value, Domain $domain): void
    {
        $this->user[strtolower($name)] = [$value, $domain];
    }

    /**
     * Answers the value of a system variable in a scope: the session value, or the global one.
     */
    public function system(Definition $definition, Scope $scope): string|int
    {
        $name = $definition->name;
        if ($scope !== Scope::Global && array_key_exists($name, $this->session)) {
            return $this->session[$name];
        }

        return $this->globals->value($definition);
    }

    /**
     * Sets the session value of a system variable.
     */
    public function set(Definition $definition, string|int $value): void
    {
        $this->session[$definition->name] = $value;
    }

    /**
     * Reads a system variable of the session by name, or null when the server has none of that name.
     */
    public function read(string $name): string|int|null
    {
        $definition = $this->catalog->find($name);

        return $definition === null ? null : $this->system($definition, Scope::Session);
    }
}
