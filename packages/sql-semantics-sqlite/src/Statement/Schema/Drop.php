<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\MissingTable;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to remove a table, a view, an index or a trigger.
 *
 * Rule: SQLITE-DROP-001. The statement removes nothing from a context and
 * provides no declaration. For a table or a view the name is resolved and the
 * resolution is the relation fact of the statement node; a name a complete
 * context does not declare is a diagnostic, unless IF EXISTS is written, in
 * which case it is the resolution AbsentRelation. A declaration context does
 * not say whether a relation is a table or a view, so `DROP VIEW` of a table
 * is not reported. Indexes and triggers are not part of a declaration
 * context; their names are kept unresolved.
 * Source: https://sqlite.org/lang_droptable.html, https://sqlite.org/lang_dropview.html,
 * https://sqlite.org/lang_dropindex.html, https://sqlite.org/lang_droptrigger.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Resolving the dropped table to its declaration
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a)');
 *     $drop = $semantics->analyze('DROP TABLE IF EXISTS main.t', [$table]);
 *     [$drop->statement->ifExists, $drop->facts->relation($drop->statement)->table->table === $table->declarations()[0]] // => [true, true]
 */
final class Drop implements Statement
{
    use Snapshot;

    /**
     * @param SchemaObjectKind $object The kind of object to remove
     * @param QualifiedName $name The object name
     * @param bool $ifExists Whether IF EXISTS is written
     */
    public function __construct(public readonly SchemaObjectKind $object, public readonly QualifiedName $name, public readonly bool $ifExists = false)
    {
        Check::input($name->catalog === null, 'An object name has at most a schema qualifier.');
    }

    /**
     * Records the resolution of a dropped table or view.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->object !== SchemaObjectKind::Table && $this->object !== SchemaObjectKind::View) {
            return;
        }
        $fact = (new TableShapes())->target($derivation, $this->name);
        if ($this->ifExists && $fact->table instanceof MissingTable) {
            $fact = new RelationFact(new RowShape([]), new AbsentRelation($this->name));
        }
        $derivation->target($this, $fact);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('DROP', $this->object->value);
        if ($this->ifExists) {
            $out->keyword('IF', 'EXISTS');
        }
        (new ObjectNames())->write($out, $this->name);
    }
}
