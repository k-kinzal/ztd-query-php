<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\Compare;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Combine\NestedLoopJoin;
use MySqlMemory\Plan\Path\JoinKind;
use MySqlMemory\Plan\Path\Source\JsonColumn;
use MySqlMemory\Plan\Path\Source\JsonTableScan;
use MySqlMemory\Plan\Path\Source\SingleRow;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Path\Transform\Materialize;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Json\JsonPath;
use MySqlMemory\Value\Json\JsonSyntax;
use ReflectionClass;
use SqlSemantics\Platform\MySql\Statement\Call\JsonTableColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\LogicalOperator;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\Dual;
use SqlSemantics\Platform\MySql\Statement\Relation\EscapedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\JoinedTable;
use SqlSemantics\Platform\MySql\Statement\Relation\JsonTable;
use SqlSemantics\Platform\MySql\Statement\Relation\NestedRelation;
use SqlSemantics\Platform\MySql\Statement\Relation\OdbcJoin;
use SqlSemantics\Platform\MySql\Statement\Relation\TableList;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Reference\Table\CommonTable;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;
use SqlSemantics\Statement\Relation;

/**
 * Plans the FROM clause of a query block: each relation occurrence is placed in the row of the block and read by an access path.
 *
 * A comma join and a join without a condition pair every row; USING and NATURAL compare the
 * columns of one name on both sides. A derived table is computed in a frame of its own, inside
 * the block's for a LATERAL one.
 *
 * @visibility MySqlMemory
 */
final class Relations
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Plans a relation and places its occurrences in the scope of the block.
     *
     * @throws SqlError When a relation cannot be read
     */
    public function plan(Relation $relation, Scope $scope): AccessPath
    {
        return match (true) {
            $relation instanceof TableReference => $this->table($relation, $scope),
            $relation instanceof DerivedTable => $this->derived($relation, $scope),
            $relation instanceof JoinedTable => $this->join($relation, $scope),
            $relation instanceof TableList => $this->list($relation, $scope),
            $relation instanceof NestedRelation, $relation instanceof OdbcJoin, $relation instanceof EscapedRelation => $this->plan($relation->relation, $scope),
            $relation instanceof Dual => new SingleRow(),
            $relation instanceof JsonTable => $this->jsonTable($relation, $scope),
            default => throw StatementError::NotSupportedYet->error('relation ' . (new ReflectionClass($relation))->getShortName()),
        };
    }

    /**
     * Plans a table reference: a stored table, a system table with the rows it holds now, or a common table expression.
     *
     * @throws SqlError When the table does not exist
     */
    public function table(TableReference $reference, Scope $scope): AccessPath
    {
        $resolution = $this->planner->compiler->facts->relation($reference)->table;
        if ($resolution instanceof CommonTable) {
            return $this->commonTable($reference, $resolution, $scope);
        }
        if (!$resolution instanceof DeclaredTable) {
            throw QueryError::NoSuchTable->error($reference->name->schema->value ?? $this->planner->settings->database, $reference->name->name->value);
        }
        $name = $resolution->table->name;
        $view = $this->planner->dictionary->schema($name->schema->value ?? $this->planner->settings->database)->views[$name->name->value] ?? null;
        if ($view !== null && $resolution->table === $view->declaration) {
            return (new Views($this->planner))->plan($reference, $view, $scope, $this);
        }
        $system = $this->planner->dictionary->system;
        $read = $system?->table($resolution->table);
        $stored = $system !== null && $read !== null ? $system->deferred($read, $this->planner->compiler->connection) : $this->planner->dictionary->table($name->schema->value ?? $this->planner->settings->database, $name->name->value);
        if ($stored === null) {
            throw QueryError::NoSuchTable->error($name->schema->value ?? $this->planner->settings->database, $name->name->value);
        }
        $definition = $stored->definition;
        if (!$definition->temporary) {
            $this->planner->dictionary->cache->open($definition->schema, $definition->name);
        }
        $selected = null;
        if ($reference->partitions !== []) {
            if ($definition->partitioning === null) {
                throw SchemaError::PartitionClauseOnNonpartitioned->error();
            }
            $selected = (new \MySqlMemory\Storage\Partitions($definition, $definition->partitioning, $this->planner->compiler->connection->context))->selected($reference->partitions);
        }
        $scope->place($reference, array_map(static fn ($column) => $column->domain, $definition->columns), array_map(static fn ($column): string => $column->name, $definition->columns), $definition);
        $scan = new TableScan($stored, $selected, $this->planner->compiler->connection->context->tableHandle($stored, $reference));
        $scope->scans[spl_object_id($reference)] = $scan;

        return $scan;
    }

    /**
     * Plans a common-table reference, retaining an independent materialized result for the statement.
     */
    public function commonTable(TableReference|\SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable $reference, CommonTable $resolution, Scope $scope): AccessPath
    {
        $plan = $this->planner->commonTable($resolution->definition);
        $merged = $resolution->definition instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression && $this->mergeable($resolution->definition->query);
        $placed = $this->shaped($reference, $plan->domains);
        $scope->place($reference, $placed, $plan->names);
        $origins = array_map(static fn (?ColumnOrigin $source): ?ColumnOrigin => $source === null ? null : new ColumnOrigin($source->schema, $source->table, $source->originalTable !== '' ? $source->originalTable : $source->table, $source->column, $source->flags, $source->exact, $source->underlying), $plan->origins);
        $stored = array_map(static fn (?ColumnOrigin $source, ?ColumnOrigin $temporary): ColumnOrigin => new ColumnOrigin($source->schema ?? '', '', $source->originalTable ?? '', '', ($temporary->flags ?? 0) | (($source->flags ?? 0) & \MySqlMemory\Result\ColumnFlag::NoDefaultValue->value)), $origins === [] ? array_fill(0, count($placed), null) : $origins, $this->planner->materialized($placed));
        $scope->merged[spl_object_id($reference)] = $merged ? $origins : $stored;
        $scope->derived[spl_object_id($reference)] = $reference->alias()->value ?? $reference->name()->name->value;

        $once = !$merged && !$plan->root instanceof Path\Source\WorkingTable && $resolution->definition instanceof \SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression && \MySqlMemory\Evaluation\Compile\Constancy::of($resolution->definition->query, $this->planner->compiler->facts, effects: false)->constant();

        return new Materialize($merged ? $plan : new QueryPlan($this->planner->settled($plan, $placed), $placed, $plan->names, $plan->origins), once: $once);
    }

    /**
     * Plans a derived table.
     */
    public function derived(DerivedTable $derived, Scope $scope): AccessPath
    {
        $plan = $this->planner->query($derived->query, $derived->lateral ? $scope : ($this->planner->settings->legacy() ? null : $scope->outer));
        $plan = new QueryPlan($plan->root, $plan->domains, $plan->names, array_map(static fn (?ColumnOrigin $origin): ?ColumnOrigin => $origin->underlying ?? $origin, $plan->origins));
        $names = $derived->columns === [] ? $plan->names : array_map(static fn ($name): string => $name->value, $derived->columns);
        $merged = $this->mergeable($derived->query);
        $placed = $this->shaped($derived, $plan->domains);
        $scope->place($derived, $placed, $names);
        $stored = $this->planner->materialized($placed);
        $legacy = $this->planner->settings->legacy();
        $stored = array_map(static fn (?ColumnOrigin $source, ?ColumnOrigin $temporary): ColumnOrigin => new ColumnOrigin($legacy ? '' : ($source->schema ?? ''), '', $source->originalTable ?? '', '', ($temporary->flags ?? 0) | (($source->flags ?? 0) & \MySqlMemory\Result\ColumnFlag::NoDefaultValue->value)), $plan->origins, $stored);
        $scope->merged[spl_object_id($derived)] = $merged ? $plan->origins : $stored;
        $scope->derived[spl_object_id($derived)] = $derived->alias->value;

        $once = !$merged && !$derived->lateral && \MySqlMemory\Evaluation\Compile\Constancy::of($derived->query, $this->planner->compiler->facts, effects: false)->constant();

        return new Materialize($merged ? $plan : new QueryPlan($this->planner->settled($plan, $placed), $placed, $plan->names, $plan->origins), $derived->lateral, $once);
    }

    /**
     * Plans JSON_TABLE: its document sees the tables before it, and its columns take their declared types.
     *
     * A counter is a signed BIGINT of ten digits. ON ERROR written before ON EMPTY is deprecated
     * with a warning. A DEFAULT value of ON EMPTY or ON ERROR is read as
     * a JSON text when the statement is resolved, and may be an array or an object only for a JSON
     * column (ER_INVALID_DEFAULT) (verified on a live 8.4 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
     *
     * @throws SqlError When the document, a path or a DEFAULT value is not valid
     */
    public function jsonTable(JsonTable $table, Scope $scope): AccessPath
    {
        $document = $this->planner->compiler->compile($table->document, $scope);
        $slots = $this->planner->compiler->facts->relation($table)->shape->slots;
        $columns = [];
        foreach ($table->columns as $column) {
            $columns[] = $this->jsonColumn($column, $slots);
        }
        [$domains, $names] = self::flattened($columns);
        $scope->place($table, $domains, $names);
        $scope->merged[spl_object_id($table)] = array_map(static fn (?ColumnOrigin $origin): ColumnOrigin => new ColumnOrigin('', '', 'json_table', '', $origin->flags ?? 0), $this->planner->materialized($domains));
        $scope->derived[spl_object_id($table)] = $table->alias->value;

        return new JsonTableScan($document, self::jsonPath($table->path->value()), $columns);
    }

    /**
     * Answers the types and names of the columns of JSON_TABLE, nested columns flattened in order.
     *
     * @param list<JsonColumn> $columns
     * @return array{list<Domain>, list<string>}
     */
    public static function flattened(array $columns): array
    {
        $domains = [];
        $names = [];
        foreach ($columns as $column) {
            if ($column->kind === 'nested') {
                [$innerDomains, $innerNames] = self::flattened($column->columns);
                array_push($domains, ...$innerDomains);
                array_push($names, ...$innerNames);
                continue;
            }
            $domains[] = $column->domain;
            $names[] = $column->name;
        }

        return [$domains, $names];
    }

    /**
     * Prepares one column of JSON_TABLE using its already resolved semantic slot.
     *
     * @param list<\SqlSemantics\Statement\Shape\OutputSlot> $slots Remaining flattened slots, consumed in written order
     * @throws SqlError When a path or a DEFAULT value is not valid
     */
    public function jsonColumn(JsonTableColumn $column, array &$slots): JsonColumn
    {
        return (new Json\Columns())->compile($column, $slots);
    }

    /**
     * Reads a path of JSON_TABLE.
     *
     * @throws SqlError When the path is not valid
     */
    public static function jsonPath(string $text): JsonPath
    {
        try {
            return JsonPath::parse($text);
        } catch (JsonSyntax $failure) {
            throw new SqlError(DataError::InvalidJsonPath, DataError::InvalidJsonPath->message($failure->position), $failure);
        }
    }

    /**
     * Answers the types of the columns of a derived table or common table as SQL Semantics resolved them, keeping a planned type it resolved only the class of.
     *
     * @param list<Domain> $planned The types of the columns of the plan of its query
     * @return list<Domain>
     */
    public function shaped(Relation $relation, array $planned): array
    {
        $domains = [];
        foreach ($this->planner->compiler->facts->relation($relation)->shape->slots as $position => $slot) {
            $type = $slot->type;
            $domains[] = $type instanceof \SqlSemantics\Statement\Type\Known && $type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain ? Domain::of($type->descriptor, $slot->nullability !== \SqlSemantics\Statement\Type\Nullability::NotNull) : $planned[$position] ?? Domain::null();
        }

        return $domains;
    }

    /**
     * Tells whether the server merges a derived query into the query that reads it instead of materializing it.
     *
     * A single query block that reads tables merges, unless it groups, aggregates, removes
     * duplicates, limits its rows or has a window; its columns are then the columns and
     * expressions it reads, with the keys of the columns. MySQL 5.6 materializes every derived
     * table, as merging came with 5.7, so their columns carry no keys there (verified on a live
     * 5.6.51 server).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/derived-table-optimization.html,
     * https://dev.mysql.com/doc/refman/5.7/en/derived-table-optimization.html.
     */
    public function mergeable(\SqlSemantics\Statement\Query $query): bool
    {
        return (new \SqlSemantics\Platform\MySql\Rules\Typing\Materialization())->mergeable($query, $this->planner->settings->release(), $this->planner->compiler->facts, $this->planner->settings->resolution()->derivedMerge);
    }

    /**
     * Plans a comma join: every row of each member paired with every row of the others.
     */
    public function list(TableList $list, Scope $scope): AccessPath
    {
        $path = $this->plan($list->members[0], $scope);
        foreach (array_slice($list->members, 1) as $member) {
            $path = new NestedLoopJoin($path, $this->plan($member, $scope), JoinKind::Inner, null, $this->lateral($member));
        }

        return $path;
    }

    /**
     * Tells whether a relation reads the columns of the relations before it: a LATERAL derived table, or JSON_TABLE.
     */
    public function lateral(Relation $relation): bool
    {
        return ($relation instanceof DerivedTable && $relation->lateral) || $relation instanceof JsonTable;
    }

    /**
     * Plans a join.
     *
     * @throws SqlError When a condition cannot be compiled
     */
    public function join(JoinedTable $join, Scope $scope): AccessPath
    {
        $before = array_keys($scope->offsets);
        $left = $this->plan($join->left, $scope);
        $middle = array_keys($scope->offsets);
        $right = $this->plan($join->right, $scope);
        $after = array_keys($scope->offsets);
        $kind = $join->operator->keepsRight() ? JoinKind::Right : ($join->operator->keepsLeft() ? JoinKind::Left : JoinKind::Inner);
        $condition = null;
        if ($join->on !== null) {
            $condition = $this->planner->compiler->compile($join->on, $scope);
        } elseif ($join->using !== [] || $join->operator->natural()) {
            $leftIds = array_values(array_diff($middle, $before));
            $rightIds = array_values(array_diff($after, $middle));
            $names = $join->operator->natural() ? $this->common($scope, $leftIds, $rightIds) : array_map(static fn ($name): string => $name->value, $join->using);
            $condition = $this->equalities($scope, $leftIds, $rightIds, $names);
        }

        $path = new NestedLoopJoin($left, $right, $kind, $condition, $this->lateral($join->right));
        if ($kind === JoinKind::Inner && $join->on !== null && $this->planner->compiler->constancy($join->on)->constant()) {
            return new Path\Transform\Filter($path, new \MySqlMemory\Evaluation\Leaf\Constant($this->planner->compiler->operators->truth(false), 1), $condition);
        }

        return $path;
    }

    /**
     * Answers the column names both sides of a natural join have, in the order of the left side.
     *
     * @param list<int> $left
     * @param list<int> $right
     * @return list<string>
     */
    public function common(Scope $scope, array $left, array $right): array
    {
        $rightNames = [];
        foreach ($right as $id) {
            foreach ($this->visible($scope, $id) as $name) {
                $rightNames[strtolower($name)] = true;
            }
        }
        $names = [];
        foreach ($left as $id) {
            foreach ($this->visible($scope, $id) as $name) {
                if (isset($rightNames[strtolower($name)]) && !in_array(strtolower($name), array_map('strtolower', $names), true)) {
                    $names[] = $name;
                }
            }
        }

        return $names;
    }

    /**
     * Answers the names of the columns of a relation occurrence that `*` shows.
     *
     * @return list<string>
     */
    public function visible(Scope $scope, int $id): array
    {
        $names = [];
        foreach ($scope->names[$id] as $position => $name) {
            if (!isset($scope->tables[$id]) || !$scope->tables[$id]->columns[$position]->invisible) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Builds the conjunction of equalities between the columns of each name on both sides.
     *
     * @param list<int> $left
     * @param list<int> $right
     * @param list<string> $names
     */
    public function equalities(Scope $scope, array $left, array $right, array $names): ?Evaluable
    {
        $condition = null;
        foreach ($names as $name) {
            $leftColumn = $this->find($scope, $left, $name);
            $rightColumn = $this->find($scope, $right, $name);
            if ($leftColumn === null || $rightColumn === null) {
                throw QueryError::BadField->error($name, 'from clause');
            }
            $comparator = Comparator::of($leftColumn->domain(), $rightColumn->domain(), '=', $this->planner->settings->connectionCollation, $this->planner->settings->release());
            $equality = new Compare(ComparisonOperator::Equal, $leftColumn, $rightColumn, $comparator, $this->planner->compiler->operators->truth(true));
            $condition = $condition === null ? $equality : new Logic(LogicalOperator::And, $condition, $equality, $this->planner->compiler->operators->truth(true));
        }

        return $condition;
    }

    /**
     * Finds a column by name among relation occurrences.
     *
     * @param list<int> $ids
     */
    public function find(Scope $scope, array $ids, string $name): ?ColumnRead
    {
        foreach ($ids as $id) {
            foreach ($scope->names[$id] as $position => $candidate) {
                if (strcasecmp($candidate, $name) === 0) {
                    return new ColumnRead($scope->columns[$id][$position], $scope->offsets[$id] + $position);
                }
            }
        }

        return null;
    }
}
