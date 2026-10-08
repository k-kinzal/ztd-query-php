<?php

declare(strict_types=1);

namespace MySqlMemory\Program;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;

/**
 * Names a running stored program declares together: its parameters, the variables of one DECLARE, or the NEW or OLD row of a trigger.
 *
 * @visibility MySqlMemory
 */
final class Row
{
    /**
     * @param Relation $relation The parameter list, variable declaration or trigger table that declares the names
     * @param list<Variable> $variables The variables, in order
     * @param string|null $alias NEW or OLD for the row of a trigger; null for parameters and variables
     * @param bool $writable Whether SET can assign the columns of the row: the NEW row of a BEFORE trigger
     */
    public function __construct(public readonly Relation $relation, public array $variables, public readonly ?string $alias = null, public readonly bool $writable = false)
    {
    }

    /**
     * Finds the variable of a name, compared without regard to letter case; a name held twice is found at its first position.
     */
    public function variable(string $name): ?Variable
    {
        foreach ($this->variables as $variable) {
            if (strcasecmp($variable->name, $name) === 0) {
                return $variable;
            }
        }

        return null;
    }

    /**
     * Answers the row as SQL Semantics reads it.
     */
    public function row(): ProgramRow
    {
        return new ProgramRow($this->relation, array_map(static fn (Variable $variable): Name => new Name($variable->name), $this->variables), array_map(static fn (Variable $variable) => $variable->domain->resolved(), $this->variables), $this->alias === null ? null : new Name($this->alias));
    }
}
