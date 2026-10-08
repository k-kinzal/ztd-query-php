<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * Names a statement of a running stored program sees: the parameters of the routine, the variables one DECLARE declares, or the NEW or OLD row of a trigger.
 *
 * A stored program runs its statements one at a time, each read where the parameters and the
 * local variables declared around it are in scope. A session that runs a statement of a program
 * gives these rows to the statement (Settings::$program): the relation that declares the names,
 * each name with the type of the value it holds, and for the row of a trigger the word NEW or
 * OLD that qualifies its columns.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html,
 * https://dev.mysql.com/doc/refman/8.4/en/trigger-syntax.html.
 *
 * @visibility public
 * @example Reading the names of a row
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramRow(new \SqlSemantics\Platform\MySql\Statement\Routine\ParameterList([]), [new \SqlSemantics\Statement\Identifier\Name('x')], [\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer()]);
 *     $row->names[0]->value // => 'x'
 */
final class ProgramRow
{
    use Snapshot;

    /**
     * @var list<Name> The names the row holds, in order
     */
    public readonly array $names;

    /**
     * @var list<Domain> The type of the value each name holds, in the order of the names
     */
    public readonly array $domains;

    /**
     * @param Relation $relation The parameter list, variable declaration or trigger table that declares the names
     * @param list<Name> $names The names the row holds, in order
     * @param list<Domain> $domains The type of the value each name holds, in the order of the names
     * @param Name|null $alias NEW or OLD for the row of a trigger, whose names are read qualified; null for parameters and variables
     */
    public function __construct(public readonly Relation $relation, array $names, array $domains, public readonly ?Name $alias = null)
    {
        $this->names = Check::listOf($names, Name::class, 'A program row holds names.');
        $this->domains = Check::listOf($domains, Domain::class, 'A program row holds the type of each name.');
        Check::input(count($this->names) === count($this->domains), 'A program row holds one type for each name.');
    }

    /**
     * Answers the position of a name in the row, compared without regard to letter case, or null when the row does not hold it; a name held twice is found at its first position.
     */
    public function position(Name $name): ?int
    {
        foreach ($this->names as $position => $candidate) {
            if (strcasecmp($candidate->value, $name->value) === 0) {
                return $position;
            }
        }

        return null;
    }
}
