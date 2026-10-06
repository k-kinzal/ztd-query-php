<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\AlterationProblems;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\RelationKinds;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Refusal\KindRefusal;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to add a column at the end of a table.
 *
 * Rule: SQLITE-ALTER-ADD-COLUMN-001. The resolution of the table is the
 * relation fact of the statement node. The node is the relation occurrence
 * the expressions of the new column definition are derived against: its row
 * shape is the columns of the existing table followed by the new column,
 * which has the declared type SQLite records and is never NULL only when it
 * is declared NOT NULL. Diagnostics, besides those of the table resolution
 * and of the expressions: a view (SQLITE-RELATION-KIND-001), which SQLite
 * refuses before it reads the new column; otherwise a column name the completely known table already
 * has; a PRIMARY KEY or UNIQUE column; a STORED generated column; a NOT NULL
 * column without a default value other than NULL; in a STRICT table a type
 * that is not one of the six standard names. The optional COLUMN keyword is
 * not written. The statement changes no declaration and provides none.
 * Source: https://sqlite.org/lang_altertable.html#alter_table_add_column.
 * Status: Implemented.
 *
 * @visibility public
 * @example Deriving the shape the new column definition sees
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite);
 *     $table = $semantics->analyze('CREATE TABLE t (a INTEGER)');
 *     $alter = $semantics->analyze('ALTER TABLE t ADD COLUMN b TEXT CHECK (b <> a)', [$table]);
 *     [count($alter->facts->relation($alter->statement)->shape->slots), $alter->toString()] // => [2, 'ALTER TABLE t ADD b TEXT CHECK (b <> a)']
 */
final class AlterAddColumn implements Statement, Relation
{
    use Snapshot;

    /**
     * @param QualifiedName $table The table to change
     * @param ColumnDefinition $column The definition of the new column
     */
    public function __construct(public readonly QualifiedName $table, public readonly ColumnDefinition $column)
    {
        Check::input($table->catalog === null, 'A table name has at most a schema qualifier.');
    }

    /**
     * Resolves the table, reports what rules the addition out and derives the expressions of the new column.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $shapes = new TableShapes();
        $existing = $shapes->target($derivation, $this->table);
        if (!(new RelationKinds())->refuse($existing, KindRefusal::AddColumn, $derivation)) {
            (new AlterationProblems())->added($existing, $this->column, $derivation);
        }
        $fact = $derivation->relation($this, $derivation->environment());
        $this->column->deriveColumn($derivation, $shapes->scope($derivation, $this, $this->table, $fact->shape, $shapes->implicitOf($fact)));
    }

    /**
     * Derives the row shape of the table with the new column.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $existing = (new TableShapes())->target($derivation, $this->table);

        return new RelationFact(new RowShape([...$existing->shape->slots, (new AlterationProblems())->slot($existing, $this->column)], $existing->shape->missing), $existing->table);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'TABLE');
        (new ObjectNames())->write($out, $this->table);
        $out->keyword('ADD')->node($this->column);
    }
}
