<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Relation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Rules\Resolution\TableShapes;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\NamedRelation;
use SqlSemantics\Statement\Snapshot;

/**
 * One occurrence of a named table, view or common table as query input.
 *
 * Rule: SQLITE-TABLE-INPUT-001. The shape and the resolution of the name
 * follow SQLITE-TABLE-SHAPE-001. An index choice restricts how SQLite may
 * read the table and changes no row.
 * Source: https://sqlite.org/lang_select.html#the_from_clause,
 * https://sqlite.org/lang_indexedby.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a named input
 *     $input = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT a FROM main.t AS x')->singleNamedInput();
 *     [$input->name()->schema?->value, $input->name()->name->value, $input->alias()?->value] // => ['main', 't', 'x']
 */
final class TableInput implements NamedRelation
{
    use Snapshot;

    /**
     * @param QualifiedName $name The relation name
     * @param Name|null $alias The correlation name
     * @param IndexChoice|null $index The demanded index
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?Name $alias = null, public readonly ?IndexChoice $index = null)
    {
    }

    /**
     * Answers the relation name.
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
        return (new TableShapes())->fact($derivation, $this->name, $environment);
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
