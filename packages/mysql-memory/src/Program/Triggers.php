<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use MySqlMemory\Dictionary\ColumnDefinition;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\Trigger;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\TimestampZones;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Fires the triggers of a table for a row a statement writes.
 *
 * The triggers of a time and an event fire in their order (FOLLOWS and PRECEDES), each for
 * every row: BEFORE ones before the row is written, with the NEW row they can change through
 * SET NEW.col, AFTER ones once it is written. INSERT fires the INSERT triggers, with NEW.col
 * of an AUTO_INCREMENT column 0 before a value is generated; a row of INSERT ... ON DUPLICATE
 * KEY UPDATE that conflicts fires BEFORE INSERT and then the UPDATE triggers, whether the row
 * changes or not; REPLACE deletes a conflicting row, firing the DELETE triggers, when the table
 * has any. UPDATE fires its triggers for every matched row, changed or not. The statements of a
 * trigger are part of the statement that fires it: an error in a trigger fails that statement
 * and undoes what it wrote (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/trigger-syntax.html,
 * https://dev.mysql.com/doc/refman/8.4/en/insert-on-duplicate.html,
 * https://dev.mysql.com/doc/refman/8.4/en/replace.html.
 *
 * @visibility MySqlMemory
 */
final class Triggers
{
    /**
     * @param Session $session The session that writes the table
     * @param StoredTable $table The table written
     */
    public function __construct(public readonly Session $session, public readonly StoredTable $table)
    {
    }

    /**
     * Answers the triggers of the table for a time and an event, in the order they fire.
     *
     * @return list<Trigger>
     */
    public function of(string $time, string $event): array
    {
        $definition = $this->table->definition;
        $schema = $definition->temporary ? null : $this->session->instance->dictionary->schema($definition->schema);

        return array_values(array_filter($schema->triggers ?? [], static fn (Trigger $trigger): bool => $trigger->table === $definition->name && $trigger->time === $time && $trigger->event === $event));
    }

    /**
     * Tells whether the table has a trigger for an event.
     */
    public function has(string $event): bool
    {
        return $this->of('BEFORE', $event) !== [] || $this->of('AFTER', $event) !== [];
    }

    /**
     * Fires the BEFORE triggers of an event and answers the NEW row as they leave it.
     *
     * @param list<int|float|string|null>|null $new The row to write, null for DELETE
     * @param list<int|float|string|null>|null $old The row written before, null for INSERT
     * @return list<int|float|string|null>|null
     */
    public function before(string $event, ?array $new, ?array $old, Context $context): ?array
    {
        foreach ($this->of('BEFORE', $event) as $trigger) {
            $new = $this->run($trigger, $new, $old, $context, true);
        }

        return $new;
    }

    /**
     * Fires the AFTER triggers of an event.
     *
     * @param list<int|float|string|null>|null $new The row written, null for DELETE
     * @param list<int|float|string|null>|null $old The row written before, null for INSERT
     */
    public function after(string $event, ?array $new, ?array $old, Context $context): void
    {
        foreach ($this->of('AFTER', $event) as $trigger) {
            $this->run($trigger, $new, $old, $context, false);
        }
    }

    /**
     * Runs one trigger for a row and answers its NEW row.
     *
     * @param list<int|float|string|null>|null $new
     * @param list<int|float|string|null>|null $old
     * @return list<int|float|string|null>|null
     */
    public function run(Trigger $trigger, ?array $new, ?array $old, Context $context, bool $before): ?array
    {
        $session = $this->session;
        $activation = new Activation('TRIGGER', $trigger->schema . '.' . $trigger->name, true, Collation::named($trigger->charsets[2]) ?? Collation::known('utf8mb4_0900_ai_ci'), $session->program);
        $relation = $trigger->statement->table;
        $rows = [];
        foreach (['NEW' => $new, 'OLD' => $old] as $alias => $values) {
            if ($values !== null) {
                $rows[$alias] = new Row($relation, $this->variables((new TimestampZones())->local($this->table, $values, $context)), $alias, $alias === 'NEW' && $before);
            }
        }
        $activation->scope = array_values($rows);
        $invocation = new Invocation($session);
        $invocation->contained(static fn () => $invocation->run($activation, $trigger->statement->body, $trigger->schema, $trigger->mode));
        if (!isset($rows['NEW'])) {
            return $new;
        }

        return array_map(static fn (Variable $variable): int|float|string|null => $variable->value, $rows['NEW']->variables);
    }

    /**
     * Answers a variable for each column of a row of the table, holding its value.
     *
     * @param list<int|float|string|null> $values
     * @return list<Variable>
     */
    public function variables(array $values): array
    {
        return array_map(static fn (ColumnDefinition $column, int $position): Variable => new Variable($column->name, $column->domain->withNullable(true), $values[$position] ?? ($column->autoIncrement ? 0 : null)), $this->table->definition->columns, array_keys($this->table->definition->columns));
    }
}
