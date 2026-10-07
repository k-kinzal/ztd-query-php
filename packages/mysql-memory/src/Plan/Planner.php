<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\Project;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\SetOperation as SetPath;
use MySqlMemory\Plan\Path\Sort;
use MySqlMemory\Plan\Path\Values;
use MySqlMemory\Plan\Path\WorkingTable;
use MySqlMemory\Typing\Aggregation;
use MySqlMemory\Typing\Materialized;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\QueryStatement;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperator;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetQuantifier;
use SqlSemantics\Platform\MySql\Statement\Query\ValuesQuery;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

/**
 * Plans the queries of a bound statement into access paths: the step of the server that builds the access path tree.
 *
 * The emulator does not optimize: tables are scanned, joins are nested loops, and grouping,
 * ordering and duplicate removal read their whole input.
 *
 * @visibility MySqlMemory
 */
final class Planner
{
    public readonly Compiler $compiler;

    public readonly Blocks $blocks;

    public readonly Relations $relations;

    /**
     * @var array<int, array{CommonTableExpression, Scope|null}> The common table expressions in reach, by object id
     */
    public array $definitions = [];

    /**
     * @var array<int, QueryPlan> The plans of common table expressions, by object id
     */
    public array $commonTables = [];

    /**
     * @var array<int, array{WorkingTable, list<\MySqlMemory\Typing\Domain>, list<string>}> The working tables of the recursive expressions being planned, by object id
     */
    public array $recursions = [];

    /**
     * @param Statement $statement The bound statement
     * @param Facts $facts The facts of the statement
     * @param Settings $settings The session settings
     * @param Connection $connection What the statement reads of its connection
     * @param Dictionary $dictionary The databases of the server
     */
    public function __construct(public readonly Statement $statement, Facts $facts, public readonly Settings $settings, Connection $connection, public readonly Dictionary $dictionary)
    {
        $this->compiler = new Compiler($facts, $settings, $this, $connection);
        $this->blocks = new Blocks($this);
        $this->relations = new Relations($this);
    }

    /**
     * Plans a query inside the scope of the block that reads it, or at the top for none.
     *
     * @throws \MySqlMemory\Error\SqlError When the query cannot be planned
     */
    public function query(Query $query, ?Scope $outer): QueryPlan
    {
        return match (true) {
            $query instanceof Select => $this->blocks->select($query, $outer),
            $query instanceof QueryExpression => $this->expression($query, $outer),
            $query instanceof ParenthesizedQuery => $this->query($query->query, $outer),
            $query instanceof QueryStatement => $this->query($query->query, $outer),
            $query instanceof SetOperation => $this->set($query, $outer),
            $query instanceof ValuesQuery => $this->values($query, $outer),
            $query instanceof ExplicitTable => $this->blocks->table($query, $outer),
            default => throw ErrorCode::NotSupportedYet->error('query ' . (new \ReflectionClass($query))->getShortName()),
        };
    }

    /**
     * Plans a query with a WITH clause, an ORDER BY or a LIMIT around its body.
     */
    public function expression(QueryExpression $query, ?Scope $outer): QueryPlan
    {
        if ($query->with instanceof With) {
            foreach ($query->with->tables as $table) {
                $this->definitions[spl_object_id($table)] = [$table, $outer];
            }
        }
        $plan = $this->query($query->body, $outer);
        if ($query->orderBy === [] && $query->limit === null) {
            return $plan;
        }
        $scope = new Scope($outer);
        $scope->place($query->body, $plan->domains, $plan->names);
        $scope->output = true;
        $expressions = array_map(static fn ($domain, int $position) => new ColumnRead($domain, $position), $plan->domains, array_keys($plan->domains));
        $keys = [];
        foreach ($query->orderBy as $item) {
            $key = $this->compiler->compile($item->expression, $scope);
            $keys[] = [count($expressions), $key->domain(), $item->direction?->value === 'DESC'];
            $expressions[] = $key;
        }
        $root = $keys === [] ? $plan->root : new Sort(new Project($plan->root, $expressions), $keys);

        return new QueryPlan($this->blocks->limit($root, $query->limit, $outer), $plan->domains, $plan->names, $plan->origins);
    }

    /**
     * Answers the plan of a common table expression, planned once in the scope it is defined in.
     *
     * @throws \MySqlMemory\Error\SqlError When the expression is not in reach
     */
    public function commonTable(Node $definition): QueryPlan
    {
        $id = spl_object_id($definition);
        if (isset($this->recursions[$id])) {
            [$working, $domains, $names] = $this->recursions[$id];

            return new QueryPlan($working, $domains, $names);
        }
        if (!isset($this->commonTables[$id])) {
            if (!isset($this->definitions[$id]) || !$definition instanceof CommonTableExpression) {
                throw ErrorCode::NotSupportedYet->error('this common table expression');
            }
            $plan = (new Recursion($this))->plan($definition, $this->definitions[$id][1]) ?? $this->query($definition->query, $this->definitions[$id][1]);
            $names = $definition->columns === [] ? $plan->names : array_map(static fn ($name): string => $name->value, $definition->columns);
            $this->commonTables[$id] = new QueryPlan($plan->root, $plan->domains, $names, $plan->origins);
        }

        return $this->commonTables[$id];
    }

    /**
     * Plans UNION, INTERSECT and EXCEPT.
     */
    public function set(SetOperation $operation, ?Scope $outer): QueryPlan
    {
        if (!$operation->left instanceof Query) {
            throw ErrorCode::NotSupportedYet->error('a leading UNION');
        }
        $left = $this->query($operation->left, $outer);
        $right = $this->query($operation->right, $outer);
        if (count($left->domains) !== count($right->domains)) {
            throw ErrorCode::WrongNumberOfColumnsInSelect->error();
        }
        $aggregation = new Aggregation($this->settings->connectionCollation);
        $domains = [];
        foreach ($left->domains as $position => $domain) {
            $domains[] = Materialized::set($aggregation->of([$domain, $right->domains[$position]], 'UNION'));
        }
        $kind = match ($operation->operator) {
            SetOperator::Union => SetKind::Union,
            SetOperator::Intersect => SetKind::Intersect,
            SetOperator::Except => SetKind::Except,
        };

        return new QueryPlan(new SetPath($kind, $operation->quantifier !== SetQuantifier::All, $left->root, $right->root, $domains), $domains, $left->names);
    }

    /**
     * Plans VALUES ROW(...), ...: columns named column_0, column_1 and so on.
     */
    public function values(ValuesQuery $query, ?Scope $outer): QueryPlan
    {
        $scope = new Scope($outer);
        $rows = [];
        $columns = [];
        foreach ($query->rows as $row) {
            $compiled = [];
            foreach ($row->values as $position => $value) {
                $evaluable = $this->compiler->compile($value, $scope);
                $compiled[] = $evaluable;
                $columns[$position][] = $evaluable->domain();
            }
            $rows[] = $compiled;
        }
        $aggregation = new Aggregation($this->settings->connectionCollation);
        $domains = array_map(static fn (array $domains) => $aggregation->of($domains, 'VALUES'), array_values($columns));
        $names = array_map(static fn (int $position): string => 'column_' . $position, array_keys($domains));

        return new QueryPlan(new Values($rows, count($domains)), $domains, $names);
    }
}
