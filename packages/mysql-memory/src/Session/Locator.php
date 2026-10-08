<?php

declare(strict_types=1);

namespace MySqlMemory\Session;

use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\InQuery;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\QuantifiedComparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Subquery\Quantifier;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Name\TableWildcard;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\OrderedSetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Finds where in a statement each column name, select list position, function call and clock call is read, and in which order the server resolves it.
 *
 * The server resolves a query block in this order: the derived tables of its FROM clause, the
 * qualifiers of its `t.*` items, its select list, WHERE, the ON conditions, GROUP BY, HAVING and ORDER BY; it names the clause it
 * resolves in the message of a name it cannot resolve. A subquery is resolved in the place it is
 * written. An UPDATE resolves WHERE, every assigned column, every value, then ORDER BY; an INSERT
 * resolves its column list or the columns of SET, the rows or the values of SET, then the
 * columns and the values of ON DUPLICATE KEY UPDATE, except that an INSERT ... SELECT resolves
 * the columns of ON DUPLICATE KEY UPDATE before the query (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/select.html.
 *
 * @visibility MySqlMemory
 */
final class Locator
{
    /**
     * @var array<int, array{string, list<int>}> The clause and resolution order of each located node, by object id
     */
    public array $places = [];

    /**
     * @var list<ColumnUse|OutputOrdinal|FunctionCall> Keeps the located nodes alive
     */
    private array $nodes = [];

    /**
     * @var list<ClockCall> Keeps the located clock calls alive
     */
    private array $clocks = [];

    /**
     * @var list<array{InQuery|QuantifiedComparison, string, list<int>}> Each IN, ANY and ALL over a subquery, with the clause and the resolution order it is read at
     */
    public array $predicates = [];

    /**
     * @var list<array{TableWildcard, list<int>}> Each `t.*` of a select list, with the resolution order its qualifier is checked at: before the items of its block
     */
    public array $wildcards = [];

    /**
     * @var list<array{Select, list<int>}> Each block whose select list writes `*` without a FROM clause, with the resolution order the star is expanded at: before the qualified stars and the items of its block
     */
    public array $stars = [];

    /**
     * @var list<array{Name, list<int>}> Each window name a query block uses, with the resolution order it is checked at: OVER name where the call is resolved, the window a specification refines after ORDER BY
     */
    public array $windows = [];

    /**
     * @var list<WindowSpec> The windows written after OVER in the query block being located, whose names are resolved after its ORDER BY
     */
    public array $specifications = [];

    /**
     * @var list<array{WindowFunction|Aggregate|GroupConcat|JsonObjectAggregate, string, list<int>}> Each window function and aggregate call, with the clause and resolution order it is read at
     */
    public array $functions = [];

    /**
     * @var list<array{SetOperation|OrderedSetOperation, list<int>}> Each set operation, with the resolution order its operands are compared at: once its right operand is resolved
     */
    public array $sets = [];

    /**
     * @var list<int> The resolution order of the query block being located
     */
    private array $block = [];

    /**
     * @var list<array{Cast, string, list<int>}> Each cast to an array outside a functional index, of a type a multi-valued index takes, with the clause and the resolution order it is read at
     */
    public array $arrays = [];

    /**
     * @var array<int, true> The casts that are the expression of a functional key part, by object id
     */
    public array $keyed = [];

    /**
     * @param bool $operandFirst Whether the operand of IN, ANY and ALL over a subquery is resolved before the subquery, and the width of the subquery checked after both, as MySQL 8.0 does
     */
    public function __construct(public readonly bool $operandFirst = false)
    {
    }

    /**
     * Locates the column names of a statement.
     */
    public function statement(Node $statement): self
    {
        if ($statement instanceof Update) {
            $this->visit($statement->where, 'where clause', [1]);
            $this->assignments($statement->assignments, [2]);
            $this->visit($statement->orderBy, 'order clause', [6]);

            return $this;
        }
        if ($statement instanceof InsertRows || $statement instanceof InsertSet) {
            $this->visit($statement->into, 'field list', [0]);
            if ($statement instanceof InsertRows) {
                $this->visit($statement->rows, 'field list', [1]);
            } else {
                $this->assignments($statement->assignments, [1]);
            }
            $this->visit($statement->alias, 'field list', [2]);
            $this->assignments($statement->onDuplicate, [3]);

            return $this;
        }
        if ($statement instanceof InsertQuery) {
            $this->visit($statement->into, 'field list', [0]);
            $this->visit(array_map(static fn (Assignment $assignment): ColumnUse => $assignment->column, $statement->onDuplicate), 'field list', [1]);
            $this->visit($statement->source, 'field list', [2]);
            $this->visit(array_map(static fn (Assignment $assignment): Scalar => $assignment->value, $statement->onDuplicate), 'field list', [3]);

            return $this;
        }
        if ($statement instanceof Delete) {
            $this->visit($statement->where, 'where clause', [2]);
            $this->visit($statement->orderBy, 'order clause', [6]);

            return $this;
        }
        $filters = (new \MySqlMemory\Evaluation\Compile\Walker())->find($statement, \SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowWhere::class);
        if ($filters !== []) {
            $this->visit($filters, 'where clause', []);

            return $this;
        }
        $this->visit($statement, 'field list', []);

        return $this;
    }

    /**
     * Locates the names of assignments as the server resolves them: every assigned column, then every value.
     *
     * @param list<Assignment> $assignments
     * @param list<int> $order
     */
    public function assignments(array $assignments, array $order): void
    {
        $this->visit(array_map(static fn (Assignment $assignment): ColumnUse => $assignment->column, $assignments), 'field list', [...$order, 0]);
        $this->visit(array_map(static fn (Assignment $assignment): Scalar => $assignment->value, $assignments), 'field list', [...$order, 1]);
    }

    /**
     * Tells whether one resolution order comes before another: element by element, a prefix first.
     *
     * @param list<int> $left
     * @param list<int> $right
     */
    public static function precedes(array $left, array $right): bool
    {
        foreach ($left as $index => $value) {
            if (!isset($right[$index])) {
                return false;
            }
            if ($value !== $right[$index]) {
                return $value < $right[$index];
            }
        }

        return count($left) < count($right);
    }

    /**
     * Answers the located column names, select list positions and function calls.
     *
     * @return list<ColumnUse|OutputOrdinal|FunctionCall>
     */
    public function nodes(): array
    {
        return $this->nodes;
    }

    /**
     * Answers the located calls of the clock functions, whose precision the server checks where it resolves them.
     *
     * @return list<ClockCall>
     */
    public function clocks(): array
    {
        return $this->clocks;
    }

    /**
     * Answers the clause and order of a column name, or null when it was not located.
     *
     * @return array{string, list<int>}|null
     */
    public function place(Node $node): ?array
    {
        return $this->places[spl_object_id($node)] ?? null;
    }

    /**
     * Locates the column names under a value, read in a clause at an order.
     *
     * The nodes under the value are visited depth first in the order of their properties; each
     * property, and each member of a list, adds its position to the order.
     *
     * @param Node|list<Node>|null $value
     * @param list<int> $order
     */
    public function visit(Node|array|null $value, string $clause, array $order): void
    {
        $values = [$value];
        $orders = [$order];
        while (($at = array_pop($orders)) !== null) {
            $current = array_pop($values);
            $children = [];
            $positions = [];
            if (is_array($current)) {
                foreach (array_values($current) as $index => $item) {
                    $children[] = $item;
                    $positions[] = [...$at, $index];
                }
            } elseif ($current instanceof Select) {
                $this->select($current, $at);
            } elseif ($current instanceof QueryExpression) {
                $this->expression($current, $clause, $at);
            } elseif ($current instanceof InQuery || $current instanceof QuantifiedComparison) {
                $this->predicates[] = [$current, $clause, $at];
                $early = self::early($current);
                $this->visit($current->operand, 'IN/ALL/ANY subquery', [...$at, $this->operandFirst ? 0 : ($early ? 2 : 1)]);
                $children[] = $current->query;
                $positions[] = [...$at, $this->operandFirst ? 1 : 0];
            } elseif ($current instanceof WindowSpec) {
                $this->record($current, $clause, $at);
                $this->specifications[] = $current;
            } elseif ($current instanceof Node) {
                $this->record($current, $clause, $at);
                $index = 0;
                foreach (get_object_vars($current) as $property) {
                    $children[] = $property;
                    $positions[] = [...$at, $index++];
                }
            }
            array_push($values, ...array_reverse($children));
            array_push($orders, ...array_reverse($positions));
        }
    }

    /**
     * Records what a node read in a clause at an order tells, before its children are visited.
     *
     * A functional key part marks its cast, ungrouped, as keyed; a cast to an array of a type a
     * multi-valued index takes, outside a key part, is recorded with its place; a column name,
     * select list position or function call is located, and so is a clock call; a window function
     * or aggregate call, and the window names, are recorded as windowed() records them.
     *
     * @param list<int> $order
     */
    public function record(Node $node, string $clause, array $order): void
    {
        if ($node instanceof ExpressionPart) {
            $keyed = $node->expression;
            while ($keyed instanceof Grouped) {
                $keyed = $keyed->operand;
            }
            $this->keyed[spl_object_id($keyed)] = true;
        }
        if ($node instanceof Cast && $node->array && $node->arrayRefusal() === null && !isset($this->keyed[spl_object_id($node)])) {
            $this->arrays[] = [$node, $clause, $order];
        }
        if ($node instanceof ColumnUse || $node instanceof OutputOrdinal || $node instanceof FunctionCall) {
            $this->places[spl_object_id($node)] = [$clause, $order];
            $this->nodes[] = $node;
        }
        if ($node instanceof ClockCall) {
            $this->places[spl_object_id($node)] = [$clause, $order];
            $this->clocks[] = $node;
        }
        if ($node instanceof SetOperation || $node instanceof OrderedSetOperation) {
            $this->sets[] = [$node, [...$order, (int) array_search('right', array_keys(get_object_vars($node)), true), PHP_INT_MAX]];
        }
        $this->windowed($node, $clause, $order);
    }

    /**
     * Records a window function or aggregate call read in a clause at an order, the window name it uses after OVER, checked where the call is resolved, and the window a specification refines, checked after the ORDER BY of its block.
     *
     * @param list<int> $order
     */
    public function windowed(Node $node, string $clause, array $order): void
    {
        if ($node instanceof WindowFunction || $node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) {
            $this->functions[] = [$node, $clause, $order];
        }
        $over = $node instanceof WindowFunction || $node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate ? $node->over : null;
        if ($over instanceof Name) {
            $this->windows[] = [$over, $order];
        }
        if ($node instanceof WindowSpec && $node->base !== null) {
            $this->windows[] = [$node->base, [...$this->block, 6, PHP_INT_MAX]];
        }
    }

    /**
     * Tells whether the server checks the width of the subquery of IN, ANY or ALL before it resolves the operand.
     *
     * The subquery is resolved first. The width is checked before the operand for ALL, and for
     * ANY with an operator other than `=`; after it for IN, `= ANY` and `<> ALL`.
     */
    public static function early(InQuery|QuantifiedComparison $predicate): bool
    {
        return $predicate instanceof QuantifiedComparison
            && !($predicate->quantifier === Quantifier::Any && $predicate->operator === ComparisonOperator::Equal)
            && !($predicate->quantifier === Quantifier::All && $predicate->operator === ComparisonOperator::NotEqual);
    }

    /**
     * Locates the column names of a query with a WITH clause, an ORDER BY or a LIMIT around its body: the ORDER BY is read in the order clause.
     *
     * @param list<int> $order
     */
    public function expression(QueryExpression $expression, string $clause, array $order): void
    {
        $this->visit($expression->with, $clause, [...$order, 0]);
        $this->visit($expression->body, $clause, [...$order, 1]);
        $this->visit($expression->orderBy, 'order clause', [...$order, 2]);
        $this->visit($expression->limit, $clause, [...$order, 3]);
    }

    /**
     * Locates the column names of a query block in the order the server resolves its clauses.
     *
     * @param list<int> $order
     */
    public function select(Select $select, array $order): void
    {
        $outer = $this->block;
        $outerSpecifications = $this->specifications;
        $this->specifications = [];
        $this->block = $order;
        $clause = new FromClause($this);
        $clause->derived($select->from, $order);
        foreach ($select->items as $item) {
            if ($item instanceof TableWildcard) {
                $this->wildcards[] = [$item, [...$order, 1, -1]];
            }
            if ($item instanceof \SqlSemantics\Platform\MySql\Statement\Query\Star && ($select->from === null || $select->from instanceof \SqlSemantics\Platform\MySql\Statement\Relation\Dual)) {
                $this->stars[] = [$select, [...$order, 1, -2]];
            }
        }
        foreach ($select->items as $index => $item) {
            $this->visit($item instanceof SelectExpression ? $item->expression : $item, 'field list', [...$order, 1, $index]);
        }
        $this->visit($select->where, 'where clause', [...$order, 2]);
        $clause->conditions($select->from, [...$order, 3]);
        $this->visit($select->groupBy, 'group statement', [...$order, 4]);
        $this->visit($select->having, 'having clause', [...$order, 5]);
        $this->visit($select->orderBy, 'order clause', [...$order, 6]);
        $this->visit($select->limit, 'field list', [...$order, 7]);
        $this->specifications($select, $order);
        $this->specifications = $outerSpecifications;
        $pending = $select->windows;
        while (($node = array_pop($pending)) !== null) {
            if ($node instanceof WindowSpec && $node->base !== null) {
                $this->windows[] = [$node->base, [...$order, 6, PHP_INT_MAX]];
            }
            foreach (get_object_vars($node) as $value) {
                foreach (is_array($value) ? $value : [$value] as $member) {
                    if ($member instanceof Node && !$member instanceof Query) {
                        $pending[] = $member;
                    }
                }
            }
        }
        $this->block = $outer;
    }

    /**
     * Locates the names of the PARTITION BY and ORDER BY of the windows of a query block, which the server resolves after its ORDER BY: the named windows of the WINDOW clause, then the windows written after OVER, in written order (verified on a live 8.4 server).
     *
     * @param list<int> $order
     */
    public function specifications(Select $select, array $order): void
    {
        $windows = [];
        foreach ($select->windows as $definition) {
            if ($definition->specification instanceof WindowSpec) {
                $windows[] = $definition->specification;
            }
        }
        foreach ([...$windows, ...$this->specifications] as $index => $window) {
            $this->visit($window->partition, 'window partition by', [...$order, 6, PHP_INT_MAX, $index, 0]);
            $this->visit($window->order, 'window order by', [...$order, 6, PHP_INT_MAX, $index, 1]);
        }
    }
}
