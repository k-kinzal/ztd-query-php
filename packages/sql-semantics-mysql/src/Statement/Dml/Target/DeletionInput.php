<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Target;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Relation\TableSource as Relation;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * The target namespace of a multiple-table DELETE and its input occurrences.
 *
 * Every target can name an input occurrence. Database selection and instance
 * name comparison are resolved later, while an intrinsically absent or repeated
 * name is excluded here. Constructing this operand does not require unrelated
 * predicates, hints or statement options.
 *
 * @visibility public
 * @example Refusing a target hidden by an alias
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), new \SqlSemantics\Statement\Identifier\Name('alias'))], [new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class DeletionInput
{
    use Snapshot;

    /**
     * @var non-empty-list<QualifiedName> The targets in written order
     */
    public readonly array $targets;

    /**
     * @var non-empty-list<Relation> The input occurrences, with outer comma lists flattened
     */
    public readonly array $tables;

    /**
     * @param list<Relation> $tables The input relation trees
     * @param list<QualifiedName>|null $targets The requested targets; null selects every visible input occurrence
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When the supplied target namespace contradicts this role
     */
    public function __construct(array $tables, ?array $targets = null)
    {
        $members = [];
        foreach (Check::listOf($tables, Relation::class, 'A multiple-table DELETE names at least one table reference.', 1) as $table) {
            array_push($members, ...($table instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableList ? $table->members : [$table]));
        }
        $this->tables = $members;
        $this->targets = Check::listOf($targets ?? (new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->names($this->tables), QualifiedName::class, 'A multiple-table DELETE names at least one table to delete from.', 1);
        foreach ($this->targets as $target) {
            Check::input($target->catalog === null, 'A table is qualified by at most a database.');
        }
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetNames())->duplicate($this->targets) === null, 'A multiple-table DELETE cannot repeat an identical target.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->missing($this->targets, $this->tables) === null, 'Every DELETE target must name a possible input occurrence.');
    }
}
