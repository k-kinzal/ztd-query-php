<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use MySqlMemory\Error\DiagnosticText;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Variable\Scope;
use MySqlMemory\Variable\UserEntry;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Definition;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach;
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;

/**
 * The variables of a session: its user variables, and the session values of the system variables.
 *
 * A user variable holds a value and domain together with its assignment collation.
 * Preparation can allocate a NULL string entry without executing an assignment.
 * An untyped NULL preserves an existing entry's type; typed NULL values change its
 * type. Their collation is replaced in MySQL 5.6 and retained from 5.7.
 * A connection starts with the session value of each system variable that has both scopes
 * set to its global value, so a later SET GLOBAL does not change it; a variable with a global
 * value only reads the global value.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/using-system-variables.html.
 *
 * @visibility MySqlMemory
 */
final class Variables
{
    /**
     * @var array<string, UserEntry> The value, domain and assignment collation of each user variable, by lower-case name
     */
    public array $user = [];

    /**
     * @var array<string, string|int|null> The session values, by lower-case name
     */
    public array $session = [];

    /**
     * @var array<string, string|int|null> The global values set when the connection started, by lower-case name; a variable with both scopes that has no session value of its own reads them
     */
    public array $connected = [];

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
     * @var list<\MySqlMemory\Account\Identity> The roles active in the session (CURRENT_ROLE())
     */
    public array $roles = [];

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
     * The rows the last statement affected, or -1 after one that answered rows or failed (ROW_COUNT()).
     */
    public int $rowCount = 0;

    /**
     * The rows the last SELECT found (FOUND_ROWS()).
     */
    public int $foundRows = 0;

    /**
     * Whether the client requested matched rows for UPDATE and one affected row for an unchanged ON DUPLICATE KEY UPDATE (CLIENT_FOUND_ROWS).
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/information-functions.html#function_row-count.
     */
    public bool $clientFoundRows = false;

    /**
     * @param SystemVariables $catalog The system variables the server knows
     * @param Globals $globals The global values of the server
     * @param \MySqlMemory\Instance $instance The server the session is connected to, whose clock, user-level locks and accounts the functions of a statement read
     */
    public function __construct(public readonly SystemVariables $catalog, public readonly Globals $globals, public readonly \MySqlMemory\Instance $instance = new \MySqlMemory\Instance())
    {
        $this->connected = $globals->values;
    }

    /**
     * Answers the value and domain of a user variable.
     *
     * @return array{int|float|string|null, Domain}
     */
    public function user(string $name): array
    {
        $entry = $this->user[strtolower($name)] ?? null;

        return $entry === null ? [null, Domain::string(0, Collation::binary(), Field::MediumBlob)] : [$entry->value, $entry->domain];
    }

    /**
     * Assigns a user variable.
     * MySQL 5.6 gives a typed NULL the prepared operand collation; an untyped NULL,
     * and all NULL assignments from 5.7, retain the entry's preceding collation.
     * Verified through PDO in Testcontainers MySQL 5.6.51 and 5.7.44.
     */
    public function assign(string $name, int|float|string|null $value, Domain $domain, ?Collation $preparedCollation = null): void
    {
        $this->checkUserName($name);
        $previous = $this->user[strtolower($name)] ?? null;
        $collation = $domain->kind->numeric() ? Collation::named((string) $this->read('collation_connection')) ?? Collation::known('utf8mb4_0900_ai_ci') : $domain->collation;
        if ($value === null) {
            $collation = $this->instance->version === '5.6.51' && $domain->kind !== Kind::Null ? $preparedCollation ?? $collation : $previous->collation ?? $collation;
            $domain = $domain->kind === Kind::Null ? $previous->domain ?? Domain::string(0, Collation::binary(), Field::MediumBlob) : $domain;
            if ($domain->kind === Kind::String) {
                $domain = $domain->withCollation($collation, Coercibility::Implicit);
            }
        }
        $this->user[strtolower($name)] = new UserEntry($value, $domain, $collation);
    }

    /**
     * Allocates an absent entry while preparing an assignment, without replacing an
     * existing entry. It remains a NULL string if the statement produces no rows.
     */
    public function allocate(string $name, Collation $collation): void
    {
        $this->checkUserName($name);
        $this->user[strtolower($name)] ??= new UserEntry(null, Domain::string(0, $collation, Field::MediumBlob)->withCollation($collation, Coercibility::Implicit), $collation);
    }

    /**
     * Refuses empty assignment names or names above 64 characters from MySQL 5.7 onward.
     * Reads of absent variables remain NULL, including names an assignment would refuse.
     * Error messages retain at most 100 bytes of the name.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/user-variables.html.
     *
     * @throws \MySqlMemory\Error\SqlError When the release refuses the assignment name
     */
    public function checkUserName(string $name): void
    {
        if ($this->instance->version !== '5.6.51' && ($name === '' || mb_strlen($name, 'UTF-8') > 64)) {
            throw StatementError::IllegalUserVariable->error((new DiagnosticText())->bytes($name, 100, $this->instance->version === '5.7.44'));
        }
    }

    /**
     * Answers the value of a system variable in a scope: the session value, the global value the connection started with, or the global one.
     */
    public function system(Definition $definition, Scope $scope): string|int|null
    {
        $name = $definition->name;
        if ($scope !== Scope::Global && in_array($name, ['last_insert_id', 'identity'], true)) {
            return $this->lastInsertId;
        }
        if ($scope !== Scope::Global && array_key_exists($name, $this->session)) {
            return $this->session[$name];
        }
        if ($scope !== Scope::Global && $name === 'pseudo_thread_id') {
            return $this->connection;
        }
        if ($scope !== Scope::Global && $definition->reach === Reach::Both) {
            return array_key_exists($name, $this->connected) ? $this->connected[$name] : $definition->default;
        }

        return $this->globals->value($definition);
    }

    /**
     * Sets the session value of a system variable.
     */
    public function set(Definition $definition, string|int|null $value): void
    {
        if (in_array($definition->name, ['last_insert_id', 'identity'], true)) {
            $this->lastInsertId = (int) $value;

            return;
        }
        $this->session[$definition->name] = $value;
    }

    /**
     * Answers the instant a statement of the session starts at: the `timestamp` the session set, or else the current time.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/server-system-variables.html#sysvar_timestamp.
     */
    public function instant(): float
    {
        $set = $this->session['timestamp'] ?? null;

        return $set === null ? $this->clock() : (float) $set;
    }

    /**
     * Answers the server clock, including elapsed sleeps and waits, independently of SET timestamp.
     */
    public function clock(): float
    {
        return $this->instance->registry->threads->now();
    }

    /**
     * Reads a system variable of the session by name, or null when the server has none of that name or the variable holds NULL.
     */
    public function read(string $name): string|int|null
    {
        $definition = $this->catalog->find($name);

        return $definition === null ? null : $this->system($definition, Scope::Session);
    }

    /**
     * Reads a system variable of the session as a count: an unsigned value beyond the largest integer, which the variable holds as a negative one, counts as the largest integer.
     */
    public function count(string $name, int $default): int
    {
        $value = $this->read($name);
        if ($value === null) {
            return $default;
        }
        $number = (int) $value;

        return $number < 0 && $this->catalog->find($name)?->shape === \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\ValueShape::Unsigned ? PHP_INT_MAX : $number;
    }
}
