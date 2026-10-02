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
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a table from column definitions.
 *
 * Rule: SQLITE-CREATE-TABLE-001. The statement provides one table
 * declaration (SQLITE-TABLE-DECLARATION-001); it executes nothing and changes
 * no context, and IF NOT EXISTS does not change what it declares. The
 * expressions inside the definition are derived in the scope of
 * SQLITE-DEFINITION-SCOPE-001, whose only visible relation is the table being
 * defined. The statement node itself is that relation occurrence: it is the
 * one place where the new table is used, a column resolved inside the
 * definition refers to it, and its relation fact holds the row shape of the
 * new table, whose slots are the declared columns. Diagnostics:
 * SQLITE-TABLE-PROBLEMS-001 and those of the constraints.
 * Source: https://sqlite.org/lang_createtable.html. Status: Implemented.
 *
 * @visibility public
 * @example Providing a declaration to a context
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
 *     $table = $create->declarations()[0];
 *     [count($table->columns), $table->columns[1]->nullability, $table->implicit[0]->column === $table->columns[0]] // => [2, \SqlSemantics\Statement\Type\Nullability::NotNull, true]
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
     */
    public function __construct(
        public readonly QualifiedName $name,
        array $columns,
        array $constraints = [],
        array $options = [],
        public readonly bool $temporary = false,
        public readonly bool $ifNotExists = false,
    ) {
        Check::input($name->catalog === null, 'A table name has at most a schema qualifier.');
        $this->columns = Check::listOf($columns, ColumnDefinition::class, 'A table definition has at least one column.', 1);
        $this->constraints = Check::listOf($constraints, ConstraintRun::class, 'Table constraints are an ordered list of constraint runs.');
        $this->options = Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
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
     * Derives the declaration the definition provides in a context.
     */
    public function declaration(Derivation $derivation): Table
    {
        $rule = new TableDeclaration();
        $domains = $rule->domains($this);

        return $rule->table($this, $domains, (new PrimaryKeyRule())->key($this, $domains, $derivation->context->columnNames), $derivation->context->profile);
    }

    /**
     * Provides the declaration, reports the problems of the definition and derives its expressions.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $rule = new TableDeclaration();
        $domains = $rule->domains($this);
        $key = (new PrimaryKeyRule())->key($this, $domains, $derivation->context->columnNames);
        $table = $rule->table($this, $domains, $key, $derivation->context->profile);
        $derivation->declare($table);
        (new TableProblems())->report($this, $domains, $key, $derivation);
        $shapes = new TableShapes();
        $shape = $derivation->target($this, new RelationFact($shapes->shape($table)))->shape;
        $scope = $shapes->scope($derivation, $this, $this->name, $shape, $shapes->implicit($table, $shape));
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
     * Derives the row shape of the table being defined: one slot per declared column.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        return new RelationFact((new TableShapes())->shape($this->declaration($derivation)));
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
        $out->symbol('(')->list([...$this->columns, ...$this->constraints])->symbol(')')->list($this->options);
    }
}
