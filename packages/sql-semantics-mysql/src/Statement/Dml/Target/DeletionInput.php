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
 * Targets retain their written order, including absent and repeated names.
 * Database selection, name comparison and target legality are resolved later. Constructing this operand does not require unrelated
 * predicates, hints or statement options.
 *
 * @visibility public
 * @example Refusing a target that an alias hides from every input
 *     new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), new \SqlSemantics\Statement\Identifier\Name('alias'))], [new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t'))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Keeping a target that another letter case may still match
 *     $input = new \SqlSemantics\Platform\MySql\Statement\Dml\Target\DeletionInput([new \SqlSemantics\Platform\MySql\Statement\Relation\TableReference(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')))], [new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('T'))]);
 *     $input->targets[0]->name->value // => 'T'
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
     * @var list<Relation|null>|null The input occurrence bound to each target; null before binding
     */
    private ?array $resolutions = null;

    /**
     * @var non-empty-list<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords> The optional .* suffix of each written target
     */
    public readonly array $wildcards;

    /**
     * @param list<Relation> $tables The input relation trees
     * @param list<QualifiedName>|null $targets The requested targets; null selects every visible input occurrence
     * @param list<Relation|null>|null $resolutions The completed target bindings, when supplied directly
     * @param list<\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords>|null $wildcards The written target suffixes; null omits every suffix
     * @throws \SqlSemantics\Diagnostic\InvalidConstruction When target operands or binding positions are malformed
     */
    public function __construct(array $tables, ?array $targets = null, ?array $resolutions = null, ?array $wildcards = null)
    {
        $members = [];
        foreach (Check::listOf($tables, Relation::class, 'A multiple-table DELETE names at least one table reference.', 1) as $table) {
            array_push($members, ...($table instanceof \SqlSemantics\Platform\MySql\Statement\Relation\TableList ? $table->members : [$table]));
        }
        $this->tables = $members;
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Query\From\RelationNames())->repeated($members) === null, 'A multiple-table DELETE names each relation once.');
        $this->targets = Check::listOf($targets ?? (new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->names($this->tables), QualifiedName::class, 'A multiple-table DELETE names at least one table to delete from.', 1);
        foreach ($this->targets as $target) {
            Check::input($target->catalog === null, 'A table is qualified by at most a database.');
        }
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetNames())->duplicate($this->targets) === null, 'A multiple-table DELETE names each target once.');
        Check::input((new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->missing($this->targets, $this->tables) === null, 'A multiple-table DELETE names only tables of its inputs.');
        $this->wildcards = Check::listOf($wildcards ?? array_fill(0, count($this->targets), \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted), \SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::class, 'DELETE target suffixes are optional wildcards.', 1);
        Check::input(count($this->wildcards) === count($this->targets), 'Every DELETE target has one wildcard choice.');
        if ($resolutions !== null) {
            $this->initializeResolutions($resolutions);
        }
    }

    /**
     * Binds the deletion targets to this DELETE's own input namespace.
     *
     * @param array<array-key, Relation|null> $resolutions Missing names retain a null binding
     */
    public function initializeResolutions(array $resolutions): void
    {
        Check::input($this->resolutions === null || $this->resolutions === $resolutions, 'A deletion input is resolved once.');
        Check::input(array_is_list($resolutions) && count($resolutions) === count($this->targets), 'Every deletion target has one resolution.');
        $occurrences = (new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->occurrences($this->tables);
        foreach ($resolutions as $relation) {
            Check::input($relation === null || in_array($relation, $occurrences, true), 'A deletion target resolves inside its own input namespace.');
        }
        $this->resolutions = $resolutions;
    }

    /**
     * Returns the input occurrence of a written deletion target.
     */
    public function relation(QualifiedName $target): ?Relation
    {
        $position = array_search($target, $this->targets, true);
        Check::input($position !== false, 'The target belongs to this DELETE.');
        Check::input(isset($this->resolutions), 'Deletion targets must be bound before execution.');
        return $this->resolutions[$position];
    }
}
