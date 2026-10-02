<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Statement\Type\ColumnDomain;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A request to create a table from column definitions.
 *
 * Rule: SQLITE-CREATE-TABLE-001. The statement provides one table declaration
 * with a column declaration per column definition, in order. It executes
 * nothing and changes no context.
 * Source: https://sqlite.org/lang_createtable.html. Status: Implemented.
 *
 * @visibility public
 * @example Providing a declaration to a context
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER, b TEXT)');
 *     count($create->declarations()[0]->columns) // => 2
 */
final class CreateTable implements Statement
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnDefinition> The column definitions in order
     */
    public readonly array $columns;

    /**
     * @param QualifiedName $name The table name
     * @param list<ColumnDefinition> $columns The column definitions in order; at least one
     */
    public function __construct(public readonly QualifiedName $name, array $columns)
    {
        $this->columns = Check::listOf($columns, ColumnDefinition::class, 'A table definition has at least one column.', 1);
    }

    /**
     * Provides the table declaration.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = new Column($column->name, new ColumnDomain($column->domain->value ?? ''), $column->notNull ? Nullability::NotNull : Nullability::Nullable);
        }
        $derivation->declare(new Table($this->name, $derivation->context->profile, $columns));
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'TABLE');
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation)->symbol('(')->list($this->columns)->symbol(')');
    }
}
