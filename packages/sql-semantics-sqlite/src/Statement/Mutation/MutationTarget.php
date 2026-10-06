<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Mutation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Relation\IndexChoice;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Snapshot;

/**
 * The table a data change statement writes to, with its optional correlation name and index choice.
 *
 * Rule: SQLITE-MUTATION-TARGET-001. The name denotes a declared relation
 * found along the schema search path; a common table of that name is never
 * the target. The shape follows SQLITE-TABLE-SHAPE-001.
 * Source: https://sqlite.org/lang_insert.html, https://sqlite.org/lang_update.html,
 * https://sqlite.org/lang_delete.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading the table a statement writes to
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $delete = $semantics->analyze('DELETE FROM t AS x WHERE x.a = 1', [$table]);
 *     [$delete->statement->target->alias()->value, $delete->facts->relation($delete->statement->target)->table->table === $table->declarations()[0]] // => ['x', true]
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
        return (new TableShapes())->fact($derivation, $this->name, $derivation->environment());
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
