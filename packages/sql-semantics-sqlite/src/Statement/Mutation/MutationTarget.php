<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Resolution\Environment;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\NamedRelation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;

/**
 * The table a data change statement writes to, with its optional correlation name and index choice.
 *
 * Rule: SQLITE-MUTATION-TARGET-001. The name denotes a declared relation
 * found along the schema search path; a common table of that name is never
 * the target. The shape follows SQLITE-TABLE-SHAPE-001.
 * Source: https://sqlite.org/lang_insert.html, https://sqlite.org/lang_update.html,
 * https://sqlite.org/lang_delete.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class MutationTarget implements NamedRelation
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name
     * @param Name|null $alias The correlation name
     * @param IndexChoice|null $index The demanded index
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?Name $alias = null, public readonly ?IndexChoice $index = null)
    {
    }

    /**
     * Answers the table name.
     */
    public function name(): QualifiedName
    {
        return $this->name;
    }

    /**
     * Answers the correlation name.
     */
    public function alias(): ?Name
    {
        return $this->alias;
    }

    /**
     * Resolves the name among the declared relations and derives the row shape of the table.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $writes = new Environment($derivation->context, relationSchema: $environment->owningSchema());

        return (new TableShapes())->fact($derivation, $this->name, $writes);
    }

    /**
     * Writes the name, the correlation name and the index choice.
     */
    public function render(Output $out): void
    {
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
        if ($this->alias !== null) {
            $out->keyword('AS')->name($this->alias, NameUse::Alias);
        }
        $out->node($this->index);
    }
}
