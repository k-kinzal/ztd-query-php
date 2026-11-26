<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\RelationFact;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\Sqlite\Model\Statement\Shape\RowShape;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Statement;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;

/**
 * A request to remove a table, a view, an index or a trigger.
 *
 * Rule: SQLITE-DROP-001. The statement removes nothing from a context and
 * provides no declaration. For a table or a view the name is resolved and the
 * resolution is the relation fact of the statement node; a name a complete
 * context does not declare is a diagnostic, unless IF EXISTS is written, in
 * which case it is the resolution AbsentRelation. `DROP TABLE` of a declared
 * view and `DROP VIEW` of a declared table are diagnostics, with or without
 * IF EXISTS (SQLITE-RELATION-KIND-001). Index and trigger removals resolve
 * at the complete IR boundary using the catalog's explicit declarations.
 * Source: https://sqlite.org/lang_droptable.html, https://sqlite.org/lang_dropview.html,
 * https://sqlite.org/lang_dropindex.html, https://sqlite.org/lang_droptrigger.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
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
        (new RelationKinds())->refuse($fact, $this->object === SchemaObjectKind::View ? KindRefusal::DropView : KindRefusal::DropTable, $derivation);
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
