<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Name\RelationReference;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Reference\Missing\IncompleteMembers;
use SqlSemantics\Statement\Reference\Table\ConditionalTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Reference\Table\UndeclaredTable;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Known;

/**
 * One occurrence of a named table, view or common table as query input.
 *
 * Rule: PG-TABLE-INPUT-001 (slice — the query family completes or replaces
 * this). The name resolves by CORE-TABLE-LOOKUP-001. A declared relation
 * contributes one slot per declared column, in order, each referring to the
 * declaration; an undeclared or conditionally resolved name contributes an
 * open shape that names the missing declaration; a missing or conflicting
 * name contributes an empty shape and a diagnostic.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-FROM. Status: Specified.
 *
 * @visibility public
 * @example Reading a named input
 *     $input = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT a FROM app.t AS x')->singleNamedInput();
 *     [$input->name()->schema?->value, $input->name()->name->value, $input->alias()?->value] // => ['app', 't', 'x']
 */
final class TableInput implements NamedRelation
{
    use Snapshot;

    /**
     * @param RelationReference $table The relation named
     * @param Name|null $alias The correlation name
     */
    public function __construct(public readonly RelationReference $table, public readonly ?Name $alias = null)
    {
    }

    /**
     * Answers the relation name.
     */
    public function name(): QualifiedName
    {
        return $this->table->name;
    }

    /**
     * Answers the correlation name.
     */
    public function alias(): ?Name
    {
        return $this->alias;
    }

    /**
     * Resolves the name and derives the row shape of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $resolution = $derivation->table($this->table->name, $environment);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }

            return new RelationFact(new RowShape($slots, $resolution->table->complete ? [] : [new IncompleteMembers($resolution->table)]), $resolution);
        }
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new RelationFact(new RowShape([], [$resolution->missing]), $resolution);
        }

        return new RelationFact(new RowShape([]), $resolution);
    }

    /**
     * Writes the relation and the correlation name after AS.
     */
    public function render(Output $out): void
    {
        $out->node($this->table);
        if ($this->alias !== null) {
            $out->keyword('AS')->name($this->alias, NameUse::Alias);
        }
    }
}
