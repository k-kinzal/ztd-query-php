<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\EnclosedQuery;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * A request to create a table from column, index and constraint definitions, a query, or both.
 *
 * Rule: MYSQL-CREATE-TABLE-001. The statement provides one table declaration
 * (MYSQL-TABLE-DECLARATION-001); it executes nothing and changes no context,
 * and neither TEMPORARY nor IF NOT EXISTS changes what it declares (a
 * temporary table hides a permanent table of the same name for the session
 * only). The statement node is the one relation occurrence of the new
 * table: its relation fact resolves to the declaration the statement
 * provides, the same object, and holds its row shape. The query of CREATE
 * TABLE ... SELECT is derived as an independent query that does not see the
 * new table. DEFAULT expressions, generated column expressions, CHECK
 * conditions, functional key parts and the partitioning are derived in the
 * scope of MYSQL-DEFINITION-SCOPE-001, whose only visible relation is that
 * occurrence. MySQL 5.x accepts TEMPORARY written more than once, and a
 * PARTITION BY clause inside the parenthesis that opens the query
 * (MYSQL-ENCLOSED-PARTITIONING-001); both are kept so the statement is
 * written back as it was. Diagnostics:
 * MYSQL-TABLE-PROBLEMS-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-select.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-temporary-table.html. Status: Implemented.
 *
 * @visibility public
 * @example Providing a declaration to a context
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20), note TEXT NULL)');
 *     $table = $create->declarations()[0];
 *     [count($table->columns), $table->columns[0]->nullability, $table->columns[2]->nullability] // => [3, \SqlSemantics\Statement\Type\Nullability::NotNull, \SqlSemantics\Statement\Type\Nullability::Nullable]
 * @example The relation fact of the statement resolves to the declaration it provides
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE TABLE t (a INT, CHECK (a > 0))');
 *     $create->facts->scalar($create->statement->elements[1]->condition->left)->resolution->slot->column === $create->declarations()[0]->columns[0] // => true
 */
final class CreateTable implements Statement, Relation
{
    use Snapshot;

    /**
     * @var list<TableElement> The column, index and constraint definitions in order; empty when no parenthesized list is written
     */
    public readonly array $elements;

    /**
     * @var list<TableOption> The table options in order
     */
    public readonly array $options;

    /**
     * @param QualifiedName $name The table name
     * @param list<TableElement> $elements The column, index and constraint definitions in order; empty when no list is written
     * @param list<TableOption> $options The table options in order
     * @param Partitioning|null $partitioning The PARTITION BY clause
     * @param TableQuery|null $query The query whose rows fill the table
     * @param int $temporaryWords How many times TEMPORARY is written; MySQL 8.0 and later accept it once
     * @param bool $ifNotExists Whether IF NOT EXISTS is written
     * @param bool $enclosed Whether the partitioning is written inside the parenthesis that opens the query (MySQL 5.x)
     */
    public function __construct(
        public readonly QualifiedName $name,
        array $elements = [],
        array $options = [],
        public readonly ?Partitioning $partitioning = null,
        public readonly ?TableQuery $query = null,
        public readonly int $temporaryWords = 0,
        public readonly bool $ifNotExists = false,
        public readonly bool $enclosed = false,
    ) {
        Check::input($name->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($temporaryWords >= 0, 'TEMPORARY is written a non-negative number of times.');
        $this->elements = Check::listOf($elements, TableElement::class, 'Table elements are an ordered list of table elements.');
        $this->options = Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
        Check::input(
            !$enclosed || ($this->elements === [] && $this->options === [] && $partitioning !== null && $query !== null && $query->duplicate === null && (new EnclosedQuery())->accepts($query->query)),
            'Only a partitioning and a query that starts with a parenthesized query are written enclosed.',
        );
    }

    /**
     * Tells whether the table is TEMPORARY.
     */
    public function temporary(): bool
    {
        return $this->temporaryWords > 0;
    }

    /**
     * Derives the definition: its declaration, its problems and the expressions inside it.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $fact = $derivation->relation($this, $derivation->environment());
        $table = $fact->table instanceof DeclaredTable ? $fact->table->table : null;
        $targets = new TableTargets();
        $scope = $targets->scope($derivation, $this, $this->name, $fact->shape, $table === null ? [] : $targets->implicit($table));
        $facts = new ElementFacts();
        foreach ($this->elements as $element) {
            $facts->element($element, $derivation, $scope, $table, $this->name);
        }
        $this->partitioning?->derivePartitioning($derivation, $scope);
    }

    /**
     * Derives the query, provides the declaration and reports the problems of the definition; the fact resolves to that declaration.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        $output = $this->query === null ? null : $derivation->query($this->query->query, $derivation->environment());
        $table = (new TableDeclaration())->table($this, $output, $derivation);
        $derivation->declare($table);
        $problems = new TableProblems();
        $problems->report($this, $table, $derivation);
        if ($output !== null) {
            $problems->selected($this, $output, $derivation);
        }

        return new RelationFact((new TableTargets())->shape($table), new DeclaredTable($table));
    }

    /**
     * Writes the definition.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE');
        for ($word = 0; $word < $this->temporaryWords; $word++) {
            $out->keyword('TEMPORARY');
        }
        $out->keyword('TABLE');
        if ($this->ifNotExists) {
            $out->keyword('IF', 'NOT', 'EXISTS');
        }
        if ($this->name->schema !== null) {
            $out->name($this->name->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->name->name, NameUse::Relation);
        if ($this->enclosed && $this->partitioning !== null && $this->query !== null) {
            (new EnclosedQuery())->write($out, $this->query->query, $this->partitioning);

            return;
        }
        if ($this->elements !== []) {
            $out->symbol('(')->list($this->elements)->symbol(')');
        }
        foreach ($this->options as $option) {
            $out->node($option);
        }
        $out->node($this->partitioning)->node($this->query);
    }
}
