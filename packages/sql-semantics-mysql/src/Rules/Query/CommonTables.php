<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Rules\Typing\Aggregation;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations;
use SqlSemantics\Platform\MySql\Rules\Typing\Materialization;
use SqlSemantics\Platform\MySql\Rules\Typing\Precision;
use SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure;
use SqlSemantics\Platform\MySql\Statement\Query\ParenthesizedQuery;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\Misuse;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\MisuseRule;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\RecursiveReference;
use SqlSemantics\Platform\MySql\Statement\Query\QueryExpression;
use SqlSemantics\Platform\MySql\Statement\Query\Set\LeadingUnion;
use SqlSemantics\Platform\MySql\Statement\Query\Set\SetOperation;
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Query\With\With;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Binds the common table expressions of a WITH clause.
 *
 * Rule: MYSQL-WITH-001. The expressions are bound in written order; each
 * sees the expressions before it and the enclosing scope, and a name
 * defined twice is reported. Under RECURSIVE an expression whose query
 * refers to its own name also sees itself: its query is a set operation
 * whose leading operands do not refer to it, and the columns are those of
 * these operands, all nullable (MYSQL-SET-FACTS-001 derives the later
 * operands with that shape). A recursive expression whose query is no such
 * set operation is reported, and a reference to it sees columns that depend
 * on the missing nonrecursive part. The shape of every expression is that
 * of its query under its column list (MYSQL-DERIVED-SHAPES-001). The
 * bindings extend the enclosing scope without opening a query level. The
 * server resolves the query of an expression only where a table reference
 * names the expression, so the problems of that query are reported at its
 * first use and an expression that is never used reports none; a name
 * defined twice is reported in any case. The duplicate also retains an aborting
 * parse notice after its query, including inside an unused definition, so later
 * spelling warnings cannot escape the failure boundary.
 * Terminates: every expression is derived once. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/with.html ("The types of the CTE
 * result columns are inferred from the column types of the nonrecursive
 * SELECT part only, and the columns are all nullable"). Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class CommonTables
{
    /**
     * Derives the expressions and answers the scope in which the statement that holds the clause sees them.
     */
    public function bind(With $with, Derivation $derivation, Environment $outer): Environment
    {
        $bindings = [];
        $seen = [];
        foreach ($with->tables as $table) {
            $key = $derivation->context->relationNames->fold($table->name->value);
            $scope = $this->extended($outer, $bindings);
            $recursive = $with->recursive && $this->refers($table->query, $table->name, $derivation);
            $problem = null;
            if ($recursive) {
                $problem = (new Recursion\Constraints())->definition($table, $derivation->context->relationNames);
                $scope = $this->extended($outer, [...$bindings, new CommonBinding($table->name, $table, new RowShape([], [new RecursiveReference($table->name)]))]);
            }
            $shape = $derivation->deferred($table, static function () use ($table, $scope, $derivation, $recursive, $problem): RowShape {
                if ($problem !== null) {
                    $derivation->report($problem);
                }

                $query = $derivation->query($table->query, $scope);
                $materialized = !$recursive && (new Materialization())->mergeable($table->query, $derivation->context->profile->grammar, Settings::of($derivation->context)->derivedMerge) ? null : $table->query;

                return (new DerivedShapes())->shape($query, $table->columns, $derivation, $materialized);
            });
            if (isset($seen[$key])) {
                $duplicate = new Misuse(MisuseRule::DuplicateCommonTable, $table->name);
                $derivation->report($duplicate);
                $derivation->warn(new ParseFailure($duplicate, true), $table);
            }
            $seen[$key] = true;
            $bindings[] = new CommonBinding($table->name, $table, $shape);
        }

        return $this->extended($outer, $bindings);
    }

    /**
     * Answers the enclosing scope with further common tables, at the same query level.
     *
     * @param list<CommonBinding> $bindings
     */
    public function extended(Environment $outer, array $bindings): Environment
    {
        return new Environment($outer->context, $outer->outer, $outer->relations, [...$outer->commonTables, ...$bindings], $outer->aliases, aggregation: $outer->aggregation, aggregatesAllowed: $outer->aggregatesAllowed, aggregateArgument: $outer->aggregateArgument, projection: $outer->projection);
    }

    /**
     * Answers the recursive expression whose self reference a set operand is derived for: a binding of the scope that still waits for its nonrecursive part and that the right operand of a set operation refers to.
     */
    public function pending(Environment $outer, Query $right, Derivation $derivation): ?CommonBinding
    {
        foreach (array_reverse($outer->commonTables) as $binding) {
            $waiting = false;
            foreach ($binding->shape->missing as $missing) {
                $waiting = $waiting || $missing instanceof RecursiveReference;
            }
            if ($waiting && $this->refers($right, $binding->name, $derivation)) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * Answers the scope with a waiting recursive expression bound to the columns of the nonrecursive part.
     */
    public function anchored(Environment $outer, CommonBinding $pending, QueryFact $anchor, Derivation $derivation): Environment
    {
        $bindings = [];
        foreach ($outer->commonTables as $binding) {
            $bindings[] = $binding === $pending ? new CommonBinding($binding->name, $binding->definition, $this->shape($pending, $anchor, $derivation)) : $binding;
        }

        return new Environment($outer->context, $outer->outer, $outer->relations, $bindings, $outer->aliases, aggregation: $outer->aggregation, aggregatesAllowed: $outer->aggregatesAllowed, aggregateArgument: $outer->aggregateArgument, projection: $outer->projection);
    }

    /**
     * Answers the columns a recursive expression sees itself with: those of the nonrecursive part under its column list, all nullable.
     */
    public function shape(CommonBinding $pending, QueryFact $anchor, Derivation $derivation): RowShape
    {
        $slots = [];
        $columns = $pending->definition instanceof CommonTableExpression ? $pending->definition->columns : [];
        foreach ($this->nullable($anchor)->shape->slots as $position => $slot) {
            $slots[] = isset($columns[$position]) ? new OutputSlot($columns[$position], $slot->type, $slot->nullability, null, $slot) : new OutputSlot($slot->name, $slot->type, $slot->nullability, null, $slot, $slot->unnamed);
        }

        return new RowShape($slots, $anchor->shape->missing);
    }

    /**
     * Answers the type a column of a recursive table keeps from its anchor: settled alone in the temporary table.
     */
    public function recursive(TypeFact $type, ?Derivation $derivation): TypeFact
    {
        $domain = $derivation === null ? null : (new Precision())->domain($type);
        $settled = $domain === null ? null : (new Aggregation(new Collations(Settings::of($derivation->context)->connection)))->of([$domain], 'UNION', $derivation);

        return $settled === null ? $type : new Known((new Materialization())->set($settled, $derivation->context->profile->grammar));
    }

    /**
     * Answers the output of a recursive set operation: the columns of its nonrecursive part, all nullable.
     */
    public function nullable(QueryFact $anchor, ?Derivation $derivation = null): QueryFact
    {
        $fields = [];
        foreach ($anchor->projection as $item) {
            $fields[] = $item instanceof Field ? new Field($item->position, new OutputSlot($item->slot->name, $this->recursive($item->slot->type, $derivation), Nullability::Nullable, null, $item->slot, $item->slot->unnamed)) : $item;
        }

        return new QueryFact($fields, $anchor->names);
    }

    /**
     * Answers the operands of the set operation chain a query is, in written order, or the query itself.
     *
     * @return non-empty-list<Query>
     */
    public function operands(Query $query): array
    {
        while (($query instanceof QueryExpression && $query->with === null) || $query instanceof ParenthesizedQuery) {
            $query = $query instanceof ParenthesizedQuery ? $query->query : $query->body;
        }
        $operands = [];
        while ($query instanceof SetOperation || $query instanceof LeadingUnion) {
            array_unshift($operands, $query->right);
            $query = $query->left;
        }
        array_unshift($operands, $query);

        return $operands;
    }

    /**
     * Tells whether a query names a table as an unqualified table reference.
     */
    public function refers(Query $query, Name $table, Derivation $derivation): bool
    {
        return (new Recursion\References())->of($query, $table, $derivation->context->relationNames) !== [];
    }
}
