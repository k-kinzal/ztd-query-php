<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Scope;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;

/**
 * Binds handler key operands before checking whether they can supply a seek value.
 *
 * Both key values and predicates see the opened table. Column reads are refused
 * after their dependencies resolve; DEFAULT(column) and VALUES(column) are
 * independent of the cursor row. Modern releases also refuse execution-bound
 * session reads. Per-call functions and assignments are not excluded by that
 * restriction. Verified against MySQL 5.7.44 and 8.4.7.
 *
 * @visibility MySqlMemory
 */
final class HandlerKeys
{
    /**
     * Places the opened relation's columns in the key and predicate scope.
     */
    public function scope(M\Dml\Handler\OpenHandler $handler, StoredTable $table): Scope
    {
        $definition = $table->definition;
        $scope = new Scope();
        $scope->place($handler, array_map(static fn ($column) => $column->domain, $definition->columns), array_map(static fn ($column): string => $column->name, $definition->columns), $definition);
        return $scope;
    }

    /**
     * Refuses a resolved key with dependencies that cannot supply a seek value.
     *
     * @throws \MySqlMemory\Error\SqlError When the key depends on a cursor row or a modern execution-bound read
     */
    public function check(Scalar $value, bool $legacy): void
    {
        if ($this->dependent($value, $legacy)) {
            throw StatementError::WrongArguments->error('HANDLER ... READ');
        }
    }

    /**
     * Inspects typed dependencies without reading SQL or evaluating their values.
     */
    public function dependent(Node $node, bool $legacy): bool
    {
        if ($node instanceof M\Expression\Access\DefaultOfColumn || $node instanceof M\Expression\Access\InsertedColumn) {
            return false;
        }
        if ($node instanceof M\Name\ColumnUse || (!$legacy && $this->session($node))) {
            return true;
        }
        if ($node instanceof M\Variable\VariableAssignment) {
            return $this->dependent($node->value, $legacy);
        }
        foreach (get_object_vars($node) as $value) {
            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node && $this->dependent($child, $legacy)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Recognizes reads deferred until execution on modern releases.
     */
    public function session(Node $node): bool
    {
        if ($node instanceof M\Variable\UserVariable || $node instanceof M\Variable\SystemVariable) {
            return true;
        }
        if ($node instanceof M\Call\ClockCall) {
            return $node->clock !== M\Call\Clock::SystemDate;
        }
        if ($node instanceof M\Call\KeywordCall) {
            return in_array($node->function, [M\Call\KeywordFunction::User, M\Call\KeywordFunction::CurrentUser], true);
        }
        if ($node instanceof M\Call\FunctionCall) {
            $name = strtoupper($node->name->value);
            return in_array($name, ['USER', 'SESSION_USER', 'SYSTEM_USER', 'CURRENT_USER', 'CONNECTION_ID', 'LAST_INSERT_ID'], true) || ($name === 'UNIX_TIMESTAMP' && $node->arguments === []);
        }
        return false;
    }
}
