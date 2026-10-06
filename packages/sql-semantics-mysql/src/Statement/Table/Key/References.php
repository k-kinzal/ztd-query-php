<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The REFERENCES clause of a foreign key or of a column: the parent table, its columns, MATCH and the referential actions.
 *
 * Rule: MYSQL-REFERENCES-001. The parent table is a table use: the node
 * records its resolution as a target. A table that refers to itself names
 * the table being defined, which the statement itself declares. At most one
 * action per event; the actions are kept in written order. MATCH is parsed
 * and ignored by InnoDB. Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-foreign-keys.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Resolving the parent table
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql);
 *     $parent = $semantics->analyze('CREATE TABLE p (id INT PRIMARY KEY)');
 *     $child = $semantics->analyze('CREATE TABLE c (p INT, FOREIGN KEY (p) REFERENCES p (id) ON DELETE CASCADE)', [$parent]);
 *     $child->facts->relation($child->statement->elements[1]->references)->table->table === $parent->declarations()[0] // => true
 */
final class References implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name>|null The parent columns, or null when no column list is written
     */
    public readonly ?array $columns;

    /**
     * @var list<ReferentialAction> The ON UPDATE and ON DELETE clauses in written order
     */
    public readonly array $actions;

    /**
     * @param QualifiedName $table The parent table
     * @param list<Name>|null $columns The parent columns; null when no column list is written, otherwise at least one
     * @param ReferenceMatch|null $match The MATCH clause, when written
     * @param list<ReferentialAction> $actions The ON UPDATE and ON DELETE clauses in written order, at most one per event
     */
    public function __construct(public readonly QualifiedName $table, ?array $columns = null, public readonly ?ReferenceMatch $match = null, array $actions = [])
    {
        Check::input($table->catalog === null, 'A table name has at most a database qualifier.');
        $this->columns = $columns === null ? null : Check::listOf($columns, Name::class, 'A written reference column list names at least one column.', 1);
        $this->actions = Check::listOf($actions, ReferentialAction::class, 'Referential actions are an ordered list.');
        Check::input(count($this->actions) < 2 || $this->actions[0]->event !== $this->actions[1]->event, 'A reference has at most one action per event.');
        Check::input(count($this->actions) <= 2, 'A reference has at most one ON UPDATE and one ON DELETE clause.');
    }

    /**
     * Resolves the parent table and records it as a table use.
     *
     * @param Table|null $defined The declaration of the table being defined, which a self reference names
     * @param QualifiedName|null $definedName The name the statement defines that table under
     */
    public function deriveReferences(Derivation $derivation, ?Table $defined = null, ?QualifiedName $definedName = null): RelationFact
    {
        return $derivation->target($this, (new TableTargets())->parent($derivation, $this->table, $defined, $definedName));
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('REFERENCES');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation);
        if ($this->columns !== null) {
            $out->symbol('(');
            foreach ($this->columns as $position => $column) {
                if ($position > 0) {
                    $out->symbol(',');
                }
                $out->name($column, NameUse::Column);
            }
            $out->symbol(')');
        }
        if ($this->match !== null) {
            $out->keyword('MATCH', $this->match->value);
        }
        foreach ($this->actions as $action) {
            $out->node($action);
        }
    }
}
