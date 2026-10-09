<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Dictionary\Dictionary;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Compiler;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Settings;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Operator\Conversion;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Combine\SetOperation as SetPath;
use MySqlMemory\Plan\Path\SetKind;
use MySqlMemory\Plan\Path\Source\Inline;
use MySqlMemory\Plan\Path\Source\WorkingTable;
use MySqlMemory\Plan\Path\Transform\Project;
use MySqlMemory\Plan\Path\Transform\Sort;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Typing\Domain;
use ReflectionClass;
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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
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
    /**
     * The compiler of the expressions of the statement.
     */
    public readonly Compiler $compiler;

    /**
     * The planner of query blocks.
     */
    public readonly Blocks $blocks;

    /**
     * The planner of FROM clauses.
     */
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
     * @var array<int, array{WorkingTable, list<Domain>, list<string>}> The working tables of the recursive expressions being planned, by object id
     */
    public array $recursions = [];

    /**
     * The query block whose rows carry the row of its FROM clause after the select list, for ON DUPLICATE KEY UPDATE of INSERT ... SELECT to read.
     */
    public ?Select $carrying = null;

    /**
     * @var array{int, Scope}|null The position the carried row starts at in each row of the block that carries it, and the scope that places its relations
     */
    public ?array $carried = null;

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
            default => throw StatementError::NotSupportedYet->error('query ' . (new ReflectionClass($query))->getShortName()),
        };
    }

    /**
     * Plans a query with a WITH clause, an ORDER BY or a LIMIT around its body.
     *
     * The server leaves the rows of a VALUES statement in written order:
     * it resolves an ORDER BY of one but does not sort by it, and like the
     * ORDER BY of a query block it leaves out a key constant for the
     * statement without evaluating it (verified on a live 8.4 server).
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
        $body = $query->body;
        while ($body instanceof ParenthesizedQuery) {
            $body = $body->query;
        }
        foreach ($body instanceof ValuesQuery ? [] : $query->orderBy as $item) {
            $key = $this->compiler->compile($item->expression, $scope);
            if ($this->compiler->constancy($item->expression)->constant() && (new Walker())->find($item->expression, Query::class) === []) {
                continue;
            }
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
                throw StatementError::NotSupportedYet->error('this common table expression');
            }
            $plan = (new Recursion($this))->plan($definition, $this->definitions[$id][1]) ?? $this->query($definition->query, $this->definitions[$id][1]);
            $names = $definition->columns === [] ? $plan->names : array_map(static fn ($name): string => $name->value, $definition->columns);
            $this->commonTables[$id] = new QueryPlan($plan->root, $plan->domains, $names, $plan->origins);
        }

        return $this->commonTables[$id];
    }

    /**
     * Plans UNION, INTERSECT and EXCEPT.
     *
     * @param list<Domain>|null $settled The types the rows are held in, those of an operation this one is an operand of; its own when null
     */
    public function set(SetOperation $operation, ?Scope $outer, ?array $settled = null): QueryPlan
    {
        if (!$operation->left instanceof Query) {
            throw StatementError::NotSupportedYet->error('a leading UNION');
        }
        $domains = $settled ?? $this->outputs($operation);
        $left = $this->operand($operation->left, $outer, $domains);
        $right = $this->operand($operation->right, $outer, $domains);
        if (count($left->domains) !== count($right->domains)) {
            throw QueryError::WrongNumberOfColumnsInSelect->error();
        }
        $kind = match ($operation->operator) {
            SetOperator::Union => SetKind::Union,
            SetOperator::Intersect => SetKind::Intersect,
            SetOperator::Except => SetKind::Except,
        };

        return new QueryPlan(new SetPath($kind, $operation->quantifier !== SetQuantifier::All, $this->settled($left, $domains), $this->settled($right, $domains), $domains), $domains, $left->names, $this->materialized($domains));
    }

    /**
     * Plans an operand of a set operation; an operand that is itself a set operation, in parentheses or not, holds its rows in the types of the outermost one.
     *
     * The server settles the operands of nested set operations in one temporary table, so
     * `SELECT 1 UNION SELECT 2.5 UNION SELECT 'x'` converts 1 straight into a string (verified on
     * live 8.0 and 8.4 servers).
     *
     * @param list<Domain> $domains The types of the columns of the outermost operation
     */
    public function operand(Query $operand, ?Scope $outer, array $domains): QueryPlan
    {
        $inner = $operand;
        while ($inner instanceof ParenthesizedQuery) {
            $inner = $inner->query;
        }

        return $inner instanceof SetOperation ? $this->set($inner, $outer, $domains) : $this->query($operand, $outer);
    }

    /**
     * Answers the rows of an operand of a set operation as the temporary table of the operation holds them: each value converted into the type of its column.
     *
     * A value whose type already is the type of the column is kept as it is; another is converted
     * as CAST converts it, so an integer gains the scale of a decimal column, a date the time of a
     * datetime one, an unsigned integer its digits in a string or decimal one, and a string the
     * character set of its column (verified on a live 8.4 server).
     *
     * @param list<Domain> $domains The types of the columns of the operation
     */
    public function settled(QueryPlan $operand, array $domains): AccessPath
    {
        $expressions = [];
        $converted = false;
        foreach ($domains as $position => $domain) {
            $from = $operand->domains[$position];
            $read = new ColumnRead($from, $position);
            $differs = $from->kind !== $domain->kind || $from->field !== $domain->field || $from->decimals !== $domain->decimals || $from->unsigned !== $domain->unsigned
                || ($domain->kind === Kind::String && $from->collation->charset !== $domain->collation->charset);
            $converted = $converted || $differs;
            $expressions[] = $differs && $from->kind !== Kind::Null ? new Conversion($read, $domain, null, $domain->collation->bytes() ? 'BINARY' : 'CHAR') : $read;
        }

        return $converted ? new Project($operand->root, $expressions) : $operand->root;
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
        $domains = $this->outputs($query);
        $names = array_map(static fn (int $position): string => 'column_' . $position, array_keys($domains));

        return new QueryPlan(new Inline($rows, count($domains)), $domains, $names);
    }

    /**
     * Answers the origins of the columns of a temporary table: none, but a blob column is flagged as one and a YEAR column as ZEROFILL.
     *
     * @param list<Domain> $domains
     * @return list<ColumnOrigin|null>
     */
    public function materialized(array $domains): array
    {
        return array_map(static fn ($domain): ?ColumnOrigin => $domain->field->blob() || $domain->field === Field::Year ? new ColumnOrigin('', '', '', '', $domain->field->blob() ? ColumnFlag::Blob->value : ColumnFlag::ZeroFill->value) : null, $domains);
    }


    /**
     * Answers the types of the columns a query returns, as SQL Semantics resolved them.
     *
     * @return list<Domain>
     *
     * @throws \MySqlMemory\Error\SqlError When SQL Semantics resolved only the class of a type
     */
    public function outputs(Query $query): array
    {
        $domains = [];
        foreach ($this->compiler->facts->query($query)->shape->slots as $slot) {
            $type = $slot->type;
            if (!$type instanceof \SqlSemantics\Statement\Type\Known || !$type->descriptor instanceof \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain) {
                throw StatementError::NotSupportedYet->error('the type of a set operation column');
            }
            $domains[] = Domain::of($type->descriptor, $slot->nullability !== \SqlSemantics\Statement\Type\Nullability::NotNull);
        }

        return $domains;
    }

}
