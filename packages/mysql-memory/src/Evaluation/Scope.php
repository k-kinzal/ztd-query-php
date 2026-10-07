<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation;

use MySqlMemory\Typing\Domain;
use SqlSemantics\Statement\Node;

/**
 * Where the relations of one query block lie in its row, for compiling the expressions of the block.
 *
 * Each relation occurrence of the FROM clause (a table reference, a derived table, a common
 * table reference) starts at an offset of the row and has the domains of its columns. An
 * aggregate of the block, once grouped, lies at its own position. A column of a relation that
 * is not in the block is looked up in the scope of the enclosing block.
 *
 * @visibility MySqlMemory
 */
final class Scope
{
    /**
     * @var array<int, int> The offset of each relation occurrence, by object id
     */
    public array $offsets = [];

    /**
     * @var array<int, list<Domain>> The column domains of each relation occurrence, by object id
     */
    public array $columns = [];

    /**
     * @var array<int, \MySqlMemory\Dictionary\TableDefinition> The stored table each base table occurrence reads, by object id
     */
    public array $tables = [];

    /**
     * @var array<int, string> The alias of each derived table and common table reference, by object id
     */
    public array $derived = [];

    /**
     * @var array<int, list<string>> The column names of each relation occurrence, by object id
     */
    public array $names = [];

    /**
     * @var array<int, Evaluable> Expressions already compiled for nodes, by object id
     */
    public array $bound = [];

    /**
     * The table whose row an INSERT was to write, read by VALUES(column) after the existing row.
     */
    public ?\MySqlMemory\Dictionary\TableDefinition $inserted = null;

    /**
     * Whether the row is the output of a query, so a select item named by alias or position is read at its position.
     */
    public bool $output = false;

    /**
     * @var list<Node> Keeps the nodes of the ids alive
     */
    public array $nodes = [];

    /**
     * @param Scope|null $outer The scope of the enclosing block
     */
    public function __construct(public readonly ?Scope $outer = null)
    {
    }

    /**
     * Places the columns of a relation occurrence at the end of the row.
     *
     * @param list<Domain> $domains
     * @param list<string> $names
     */
    public function place(Node $relation, array $domains, array $names = [], ?\MySqlMemory\Dictionary\TableDefinition $table = null): int
    {
        $offset = $this->width();
        $id = spl_object_id($relation);
        $this->offsets[$id] = $offset;
        $this->columns[$id] = $domains;
        $this->names[$id] = $names;
        if ($table !== null) {
            $this->tables[$id] = $table;
        }
        $this->nodes[] = $relation;

        return $offset;
    }

    /**
     * Answers the width of the row: the columns of every placed relation.
     */
    public function width(): int
    {
        $width = 0;
        foreach ($this->columns as $domains) {
            $width += count($domains);
        }

        return $width;
    }

    /**
     * Finds the block of a relation occurrence: the number of blocks out, and its scope; null when none places it.
     *
     * @return array{int, Scope}|null
     */
    public function locate(Node $relation): ?array
    {
        $depth = 0;
        for ($scope = $this; $scope !== null; $scope = $scope->outer) {
            if (isset($scope->offsets[spl_object_id($relation)])) {
                return [$depth, $scope];
            }
            $depth++;
        }

        return null;
    }

    /**
     * Binds a node to an expression compiled for it, such as an aggregate or a grouped expression.
     */
    public function bind(Node $node, Evaluable $evaluable): void
    {
        $this->bound[spl_object_id($node)] = $evaluable;
        $this->nodes[] = $node;
    }

    /**
     * Answers the expression bound to a node in this block or an enclosing one, with its depth.
     *
     * @return array{int, Evaluable}|null
     */
    public function bound(Node $node): ?array
    {
        $depth = 0;
        for ($scope = $this; $scope !== null; $scope = $scope->outer) {
            if (isset($scope->bound[spl_object_id($node)])) {
                return [$depth, $scope->bound[spl_object_id($node)]];
            }
            $depth++;
        }

        return null;
    }

    /**
     * Answers a scope with the same placement for the rows after grouping, whose bound expressions are its own.
     */
    public function grouped(): self
    {
        $scope = new self($this->outer);
        $scope->offsets = $this->offsets;
        $scope->columns = $this->columns;
        $scope->names = $this->names;
        $scope->tables = $this->tables;
        $scope->derived = $this->derived;
        $scope->nodes = $this->nodes;

        return $scope;
    }
}
