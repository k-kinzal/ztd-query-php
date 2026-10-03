<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ObjectNames;
use SqlSemantics\Platform\Sqlite\Rules\Definition\PrimaryKeyRule;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableDeclaration;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableProblems;
use SqlSemantics\Platform\Sqlite\Rules\Definition\TableShapes;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnDefinition;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\ConstraintRun;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOption;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableOptionKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a table from column definitions.
 *
 * Rule: SQLITE-CREATE-TABLE-001. The statement provides one table
 * declaration (SQLITE-TABLE-DECLARATION-001); it executes nothing and changes
 * no context, and IF NOT EXISTS does not change what it declares. The
 * statement node is the one relation occurrence of the new table: its
 * relation fact resolves to the declaration the statement provides, the
 * same object, and holds the row shape of the new table, whose slots are the
 * declared columns. A comma the grammar admits before the first table
 * option is kept as written. The expressions inside the definition are derived in the
 * scope of SQLITE-DEFINITION-SCOPE-001, whose only visible relation is that
 * occurrence, so a column resolved inside the definition reaches the declared
 * column. Diagnostics: SQLITE-TABLE-PROBLEMS-001 and those of the columns and
 * constraints.
 * Source: https://sqlite.org/lang_createtable.html. Status: Implemented.
 *
 * @visibility public
 * @example Providing a declaration to a context
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
 *     $table = $create->declarations()[0];
 *     [count($table->columns), $table->columns[1]->nullability, $table->implicit[0]->column === $table->columns[0]] // => [2, \SqlSemantics\Statement\Type\Nullability::NotNull, true]
 * @example The relation fact of the statement resolves to the declaration it provides
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b CHECK (a > 0))');
 *     $check = $create->statement->columns[1]->constraints[0];
 *     [$create->facts->relation($create->statement)->table->table === $create->declarations()[0], $create->facts->scalar($check->expression->left)->resolution->slot->column === $create->declarations()[0]->columns[0]] // => [true, true]
 */
final class CreateTable implements Statement, Relation
{
    use Snapshot;

    /**
     * @var non-empty-list<ColumnDefinition> The column definitions in order
     */
    public readonly array $columns;

    /**
     * @var list<ConstraintRun> The table constraints as comma-separated runs, in order
     */
    public readonly array $constraints;

    /**
     * @var list<TableOption> The table options in order
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The table name
     * @param list<ColumnDefinition> $columns The column definitions in order; at least one
     * @param list<ConstraintRun> $constraints The table constraints as comma-separated runs, in order
     * @param list<TableOption> $options The table options in order
     * @param bool $temporary Whether TEMP or TEMPORARY is written
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param bool $optionsComma Whether a comma is written between the closing parenthesis and the first table option; the grammar admits one and SQLite ignores it
     */
    public function __construct(
        public readonly QualifiedName $name,
        array $columns,
        array $constraints = [],
        array $options = [],
        public readonly bool $temporary = false,
        public readonly bool $ifNotExists = false,
        public readonly bool $optionsComma = false,
    ) {
        Check::input($name->catalog === null, 'A table name has at most a schema qualifier.');
        $this->columns = Check::listOf($columns, ColumnDefinition::class, 'A table definition has at least one column.', 1);
        $this->constraints = Check::listOf($constraints, ConstraintRun::class, 'Table constraints are an ordered list of constraint runs.');
        $this->options = Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
        Check::input(!$optionsComma || $this->options !== [], 'A comma before the table options needs a table option after it.');
    }

    /**
     * Tells whether the table is defined WITHOUT ROWID.
     */
    public function withoutRowid(): bool
    {
        foreach ($this->options as $option) {
            if ($option->kind() === TableOptionKind::WithoutRowid) {
                return true;
            }
        }

        return false;
    }

    /**
     * Tells whether the table is defined STRICT.
     */
    public function strict(): bool
    {
        foreach ($this->options as $option) {
            if ($option->kind() === TableOptionKind::Strict) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derives the definition: its declaration, its problems and the expressions inside it.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->relation($this, $derivation->environment());
        $shapes = new TableShapes();
        $scope = $shapes->scope($derivation, $this, $this->name, $fact->shape, $shapes->implicitOf($fact));
        foreach ($this->columns as $column) {
            $column->deriveColumn($derivation, $scope);
        }
        foreach ($this->constraints as $run) {
            foreach ($run->items as $constraint) {
                $constraint->deriveConstraint($derivation, $scope);
            }
        }
    }

    /**
     * Provides the declaration and reports the problems of the definition; the fact resolves to that declaration and holds its row shape.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $rule = new TableDeclaration();
        $domains = $rule->domains($this);
        $key = (new PrimaryKeyRule())->key($this, $domains, $derivation->context->columnNames);
        $table = $rule->table($this, $domains, $key, $derivation->context->profile);
        $derivation->declare($table);
        (new TableProblems())->report($this, $domains, $key, $derivation);

        return new RelationFact((new TableShapes())->shape($table), new DeclaredTable($table));
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        if ($this->temporary) {
            $out->keyword('TEMP');
        }
        $out->keyword('TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        (new ObjectNames())->write($out, $this->name);
        $out->symbol('(')->list([...$this->columns, ...$this->constraints])->symbol(')');
        if ($this->optionsComma) {
            $out->symbol(',');
        }
        $out->list($this->options);
    }
}
