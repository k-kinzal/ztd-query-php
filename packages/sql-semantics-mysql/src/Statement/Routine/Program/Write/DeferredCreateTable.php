<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Write;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Routine\ProgramScope;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\EnclosedQuery;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableDeclaration;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableProblems;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TableTargets;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\TypeNotices;
use SqlSemantics\Platform\MySql\Statement\Partition\Partitioning;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\TableElement;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\RelationFact;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Snapshot;

/**
 * A stored table-creation instruction retaining a deferred source or generation role.
 *
 * A definition retains the complete header and query without opening tables.
 * When invoked, the MERGE/query role condition precedes query preparation.
 * Prepared input instead prepares the query before retaining this instruction.
 * MySQL 5.7 compound generation expressions with nested subqueries also retain
 * their complete column declarations. Destination existence precedes their
 * invocation condition; IF NOT EXISTS can complete against an existing table.
 * This is a program template, not an ordinary table-creation statement.
 *
 * @example Retaining a stored creation with both source roles
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('CREATE PROCEDURE p() CREATE TABLE t UNION=() SELECT 1');
 *     $operation->statement->body instanceof \SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable // => true
 *
 * @visibility public
 */
final class DeferredCreateTable implements ProgramStatement, Relation
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
        (new \SqlSemantics\Platform\MySql\Rules\TableChange\TableIdentity())->check($name);
        Check::input($name->catalog === null, 'A table name has at most a database qualifier.');
        Check::input($temporaryWords >= 0, 'TEMPORARY is written a non-negative number of times.');
        $this->elements = Check::listOf($elements, TableElement::class, 'Table elements are an ordered list of table elements.');
        $this->options = Check::listOf($options, TableOption::class, 'Table options are an ordered list of table options.');
        Check::input(
            !$enclosed || ($this->elements === [] && $this->options === [] && $partitioning !== null && $query !== null && $query->duplicate === null && (new EnclosedQuery())->accepts($query->query)),
            'Only a partitioning and a query that starts with a parenthesized query are written enclosed.',
        );
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Name\ColumnQualifiers())->check($name, $this->elements);
        Check::input(($query !== null && array_filter($this->options, static fn ($option): bool => $option instanceof \SqlSemantics\Platform\MySql\Statement\Table\Option\UnionOption) !== []) || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\Generation\LegacySubqueries())->columns($this->elements), 'A deferred table creation retains MERGE sources together with a query or a deferred generation declaration.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
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
    public function deriveProgram(Derivation $derivation, ProgramScope $scope): void
    {
        $fact = $derivation->relation($this, $scope->environment);
        $table = $fact->table instanceof DeclaredTable ? $fact->table->table : null;
        $targets = new TableTargets();
        $scope = $targets->scope($derivation, $this, $this->name, $fact->shape, $table === null ? [] : $targets->implicit($table));
        $facts = new ElementFacts();
        foreach ($this->elements as $element) {
            $facts->element($element, $derivation, $scope, $table, $this->name);
        }
        $this->partitioning?->derivePartitioning($derivation, $scope);
        $derivation->orderWarnings($this);
    }

    /**
     * Derives the query, provides the declaration and reports the problems of the definition; the fact resolves to that declaration.
     */
    public function deriveRelation(Derivation $derivation, Environment $environment): RelationFact
    {
        foreach ($this->elements as $element) {
            (new ElementFacts())->names($element, $derivation, $this->name);
            if ($element instanceof ColumnDefinition) {
                (new TypeNotices())->type($element->specification->dataType(), $derivation);
                (new \SqlSemantics\Platform\MySql\Rules\Typing\TypeLimits())->check($element->specification->dataType(), $element->name->column->value, $derivation);
            } elseif ($element instanceof \SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition) {
                (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\IndexOptions())->parsers($element->options, $derivation);
            }
        }
        (new ElementFacts())->options($this->options, $derivation);
        $output = $this->query === null ? null : $derivation->query($this->query->query, $environment);
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
