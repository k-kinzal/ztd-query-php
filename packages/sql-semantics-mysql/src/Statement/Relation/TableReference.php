<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
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
 * Slice of the query family: it has no partition selection, index hints or
 * sampling yet and is completed or replaced by that family.
 *
 * Rule: MYSQL-TABLE-REFERENCE-001. The name resolves by
 * CORE-TABLE-LOOKUP-001 in the current database or the database written. A
 * declared table contributes one slot per declared column, in order, each
 * referring to the declaration; an undeclared or conditionally resolved
 * name contributes an open shape that names the missing declaration; a
 * missing or conflicting name contributes an empty complete shape and a
 * diagnostic. Source: https://dev.mysql.com/doc/refman/8.4/en/join.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a named input
 *     $input = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a FROM shop.t AS x')->singleNamedInput();
 *     [$input->name()->schema?->value, $input->name()->name->value, $input->alias()?->value] // => ['shop', 't', 'x']
 */
final class TableReference implements NamedRelation
{
    use Snapshot;

    /**
     * @param QualifiedName $name The table name with its optional database
     * @param Name|null $alias The correlation name
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?Name $alias = null)
    {
        Check::input($name->catalog === null, 'A table is qualified by at most a database.');
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
     * Resolves the name and derives the row shape of the occurrence.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $resolution = $derivation->table($this->name, $environment);
        if ($resolution instanceof DeclaredTable) {
            $slots = [];
            foreach ($resolution->table->columns as $column) {
                $slots[] = new OutputSlot($column->name, new Known($column->type), $column->nullability, $column);
            }

            return new RelationFact(new RowShape($slots), $resolution);
        }
        if ($resolution instanceof UndeclaredTable || $resolution instanceof ConditionalTable) {
            return new RelationFact(new RowShape([], [$resolution->missing]), $resolution);
        }

        return new RelationFact(new RowShape([]), $resolution);
    }

    /**
     * Writes the name and the correlation name.
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
    }
}
