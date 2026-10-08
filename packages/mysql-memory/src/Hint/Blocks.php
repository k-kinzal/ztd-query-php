<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Relation;

/**
 * Finds the query blocks of a statement, numbered as the server numbers them, with the orders the server reads their hints in.
 *
 * Blocks are numbered in the order their keywords are written; a common table expression is a
 * new block at each reference to it, read where the reference is written, and a view is not
 * looked into. The statement of INSERT, UPDATE and DELETE is block 1, and the first block of
 * the query INSERT reads shares it; SET, DO and CALL are a block 1 of their own, so their
 * subqueries start at block 2. The hint comment of a block is read after those of the blocks
 * written inside it (contextualized). The names of a block are resolved before those of its
 * derived tables, and then those of the subqueries of its select list, WHERE, ON, GROUP BY,
 * HAVING, WINDOW, QUALIFY, ORDER BY and LIMIT, in that order (resolved). A table is known to a
 * block by its alias, or its name; the indexes of a base table are those of its definition, and
 * a derived table, a view or a JSON_TABLE has none (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-query-block-naming.
 *
 * @visibility MySqlMemory\Hint
 */
final class Blocks
{
    /**
     * @var array<int, QueryBlock> The blocks, by number
     */
    public array $blocks = [];

    /**
     * @var list<int> The blocks in the order their hint comments are read
     */
    public array $contextualized = [];

    /**
     * @var list<int> The blocks in the order their names are resolved
     */
    public array $resolved = [];

    /**
     * @var list<array<string, CommonTableExpression>> The common table expressions in scope, the innermost last, by name
     */
    public array $scopes = [];

    /**
     * @var array<int, true> The common table expressions being read, by object id, whose references inside are plain tables
     */
    public array $expanding = [];

    /**
     * The block 1 of an INSERT that the first block of its query shares, until that block is read.
     */
    public ?QueryBlock $shared = null;

    /**
     * @param Dictionary $dictionary The tables of the server
     * @param string $database The current database, or the empty string
     */
    public function __construct(public readonly Dictionary $dictionary, public readonly string $database)
    {
    }

    /**
     * Reads the query blocks of a statement; a statement other than a query, INSERT, REPLACE, UPDATE, DELETE, CREATE TABLE ... SELECT, SET, DO, CALL and their EXPLAIN has none.
     */
    public function read(Node $statement): self
    {
        if ($statement instanceof Explain) {
            return $this->read($statement->statement);
        }
        if ($statement instanceof Query) {
            $this->resolved = $this->query($statement, true);
        } elseif ($statement instanceof InsertQuery || $statement instanceof InsertRows || $statement instanceof InsertSet) {
            $this->insert($statement);
        } elseif ($statement instanceof Update || $statement instanceof Delete || $statement instanceof MultipleDelete) {
            $this->change($statement);
        } elseif ($statement instanceof CreateTable && $statement->query !== null) {
            $this->resolved = $this->query($statement->query->query, false);
        } elseif ($statement instanceof SetVariables || $statement instanceof Evaluation || $statement instanceof ProcedureCall) {
            $this->open(false);
            $this->resolved = [1, ...$this->subqueries($statement)];
            $this->contextualized[] = 1;
        }

        return $this;
    }

    /**
     * Reads INSERT and REPLACE: block 1 holds the table written to, and the first block of the query read, if any.
     */
    public function insert(InsertQuery|InsertRows|InsertSet $statement): void
    {
        $block = $this->open(false);
        $block->hints = $statement->into->hints;
        $block->tables[] = $this->named($statement->into->table->name, null);
        if ($statement instanceof InsertQuery) {
            $this->shared = $block;
            $sequence = $this->query($statement->source, false);
            $this->shared = null;
            if (!in_array(1, $this->contextualized, true)) {
                $this->contextualized[] = 1;
            }
            $this->resolved = [...$sequence, ...$this->subqueries(...$statement->onDuplicate)];

            return;
        }
        $parts = $statement instanceof InsertRows ? $statement->rows : $statement->assignments;
        $this->resolved = [1, ...$this->subqueries(...$parts, ...$statement->onDuplicate)];
        $this->contextualized[] = 1;
    }

    /**
     * Reads UPDATE and DELETE: block 1 holds the tables of the statement.
     */
    public function change(Update|Delete|MultipleDelete $statement): void
    {
        $this->enter($statement->with);
        $block = $this->open(false);
        $block->hints = $statement->hints;
        $derived = [];
        $on = [];
        if ($statement instanceof Delete) {
            $block->tables[] = $this->named($statement->table->name, $statement->table->alias?->value);
        } else {
            foreach ($statement->tables as $table) {
                [$inner, $joined] = $this->relation($table, $block);
                $derived = [...$derived, ...$inner];
                $on = [...$on, ...$joined];
            }
        }
        $values = $statement instanceof Update ? $this->subqueries(...$statement->assignments) : [];
        $where = $this->subqueries($statement->where);
        $order = $statement instanceof MultipleDelete ? [] : $this->subqueries(...$statement->orderBy);
        $this->contextualized[] = 1;
        $this->resolved = [1, ...$derived, ...$values, ...$where, ...$on, ...$order];
        $this->leave($statement->with);
    }

    /**
     * Reads the blocks of a query and answers them in the order their names are resolved.
     *
     * @param bool $top Whether the query is the statement, so that its first block is a SELECT statement
     *
     * @return list<int>
     */
    public function query(Query|LeadingUnion $query, bool $top): array
    {
        if ($query instanceof Select) {
            return $this->select($query, $top);
        }
        if ($query instanceof ExplicitTable || $query instanceof ValuesQuery) {
            $block = $this->open($top);
            if ($query instanceof ExplicitTable) {
                $block->tables[] = $this->named($query->table, null);
            }
            $rows = $query instanceof ValuesQuery ? $this->subqueries(...$query->rows) : [];
            $this->contextualized[] = $block->number;

            return [$block->number, ...$rows];
        }
        if ($query instanceof ParenthesizedQuery || $query instanceof QueryStatement) {
            return $this->query($query->query, $top);
        }
        if ($query instanceof QueryExpression) {
            $this->enter($query->with);
            $sequence = [...$this->query($query->body, $top), ...$this->subqueries(...$query->orderBy), ...$this->subqueries($query->limit)];
            $this->leave($query->with);

            return $sequence;
        }
        if ($query instanceof SetOperation || $query instanceof LeadingUnion) {
            return [...$this->query($query->left, $top), ...$this->query($query->right, false)];
        }
        if ($query instanceof OrderedSetOperation) {
            return [...$this->query($query->left, $top), ...$this->query($query->right, false), ...$this->subqueries(...$query->orderBy), ...$this->subqueries($query->limit)];
        }

        return $this->subqueries($query);
    }

    /**
     * Reads one SELECT block and the blocks written inside it.
     *
     * @return list<int>
     */
    public function select(Select $select, bool $top): array
    {
        $block = $this->open($top);
        $block->hints = [...$select->hints, ...$block->hints];
        $items = $this->subqueries(...$select->items);
        [$derived, $on] = $this->relation($select->from, $block);
        $where = $this->subqueries($select->where);
        $group = $this->subqueries($select->groupBy);
        $having = $this->subqueries($select->having);
        $windows = $this->subqueries(...$select->windows);
        $qualify = $this->subqueries($select->qualify);
        $order = $this->subqueries(...$select->orderBy);
        $limit = $this->subqueries($select->limit);
        $this->contextualized[] = $block->number;

        return [$block->number, ...$derived, ...$items, ...$where, ...$on, ...$group, ...$having, ...$windows, ...$qualify, ...$order, ...$limit];
    }

    /**
     * Reads the tables of a FROM clause into a block: answers the blocks of its derived tables and those of its ON conditions, each in resolution order.
     *
     * @return array{list<int>, list<int>}
     */
    public function relation(?Relation $relation, QueryBlock $block): array
    {
        if ($relation instanceof TableReference) {
            $alias = $relation->alias?->value;
            $cte = $relation->name->schema === null ? $this->common($relation->name->name->value) : null;
            if ($cte === null) {
                $block->tables[] = $this->named($relation->name, $alias);

                return [[], []];
            }
            $block->tables[] = [$alias ?? $relation->name->name->value, []];
            $this->expanding[spl_object_id($cte)] = true;
            $sequence = $this->query($cte->query, false);
            unset($this->expanding[spl_object_id($cte)]);

            return [$sequence, []];
        }
        if ($relation instanceof DerivedTable) {
            $block->tables[] = [$relation->alias->value ?? '', []];

            return [$this->query($relation->query, false), []];
        }
        if ($relation instanceof JsonTable) {
            $block->tables[] = [$relation->alias->value ?? '', []];

            return [[], []];
        }
        if ($relation instanceof JoinedTable) {
            [$left, $leftOn] = $this->relation($relation->left, $block);
            [$right, $rightOn] = $this->relation($relation->right, $block);

            return [[...$left, ...$right], [...$leftOn, ...$rightOn, ...$this->subqueries($relation->on)]];
        }
        if ($relation instanceof TableList) {
            $derived = [];
            $on = [];
            foreach ($relation->members as $member) {
                [$inner, $joined] = $this->relation($member, $block);
                $derived = [...$derived, ...$inner];
                $on = [...$on, ...$joined];
            }

            return [$derived, $on];
        }
        if ($relation instanceof NestedRelation || $relation instanceof EscapedRelation || $relation instanceof OdbcJoin) {
            return $this->relation($relation->relation, $block);
        }

        return [[], []];
    }

    /**
     * Reads the blocks of the subqueries written in some nodes, in written order.
     *
     * @return list<int>
     */
    public function subqueries(?Node ...$nodes): array
    {
        $sequence = [];
        foreach ($nodes as $node) {
            if ($node === null) {
                continue;
            }
            foreach ((new Walker())->find($node, Query::class, false) as $query) {
                if ($query !== $node) {
                    $sequence = [...$sequence, ...$this->query($query, false)];
                }
            }
        }

        return $sequence;
    }

    /**
     * Answers the next block: a new one, or block 1 of the INSERT whose query this is the first block of.
     */
    public function open(bool $top): QueryBlock
    {
        if ($this->shared !== null) {
            $block = $this->shared;
            $this->shared = null;

            return $block;
        }
        $block = new QueryBlock(count($this->blocks) + 1, $top);
        $this->blocks[$block->number] = $block;

        return $block;
    }

    /**
     * Answers a table of a block by a name: its alias, or its name, with the indexes of its definition.
     *
     * @return array{string, list<string>}
     */
    public function named(QualifiedName $name, ?string $alias): array
    {
        $stored = $this->dictionary->table($name->schema->value ?? $this->database, $name->name->value);
        $indexes = $stored === null ? [] : array_map(static fn (\MySqlMemory\Dictionary\Key $key): string => $key->name, $stored->definition->keys);

        return [$alias ?? $name->name->value, $indexes];
    }

    /**
     * Answers the common table expression a table name refers to, or null when none in scope has the name or it is being read.
     */
    public function common(string $name): ?CommonTableExpression
    {
        for ($index = count($this->scopes) - 1; $index >= 0; $index--) {
            $cte = $this->scopes[$index][$name] ?? null;
            if ($cte !== null) {
                return isset($this->expanding[spl_object_id($cte)]) ? null : $cte;
            }
        }

        return null;
    }

    /**
     * Brings the common table expressions of a WITH clause into scope.
     */
    public function enter(?WithClause $with): void
    {
        if (!$with instanceof With) {
            return;
        }
        $scope = [];
        foreach ($with->tables as $cte) {
            $scope[$cte->name->value] = $cte;
        }
        $this->scopes[] = $scope;
    }

    /**
     * Takes the common table expressions of a WITH clause out of scope.
     */
    public function leave(?WithClause $with): void
    {
        if ($with instanceof With) {
            array_pop($this->scopes);
        }
    }
}
