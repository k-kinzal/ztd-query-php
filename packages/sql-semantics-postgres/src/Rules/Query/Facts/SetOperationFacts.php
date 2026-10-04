<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Query\Facts;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Carriers;
use SqlSemantics\Platform\PostgreSql\Rules\Query\UnknownOutputs;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\ArityRule;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SetOperation;
use SqlSemantics\Platform\PostgreSql\Statement\Query\With\RecursiveDefinition;
use SqlSemantics\Resolution\CommonBinding;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Shape\RowShape;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * Derives the facts of UNION, INTERSECT and EXCEPT.
 *
 * Rule: PG-SET-OPERATION-001. Both operands are derived in the enclosing
 * environment. The output has the names of the first operand; the type of a
 * column is the common type of the operand columns (PG-UNIFICATION-001,
 * untyped literals as `unknown`, PG-UNKNOWN-OUTPUT-001), and a column can be
 * NULL when it can in an operand. Operands of different widths and types
 * that cannot be matched are reported. When the set operation is the query
 * of a recursive common table or view (RecursiveDefinition) that refers to
 * itself, the second operand
 * sees that table with the columns of the first operand, renamed by the
 * column list of the table, each able to be NULL because later rounds of the
 * recursion feed back their own rows. An operand whose columns are not all
 * known leaves the output open. Terminates: two operands; no fixpoint is
 * computed.
 * Source: https://www.postgresql.org/docs/17/queries-union.html,
 * https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-RECURSIVE. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class SetOperationFacts
{
    /**
     * Derives both operands and answers the combined output.
     */
    public function derive(SetOperation $operation, Derivation $derivation, Environment $outer): QueryFact
    {
        $left = $derivation->query($operation->left, $outer);
        $right = $derivation->query($operation->right, $this->rebound($operation, $left, $outer));

        return new QueryFact($this->combined($operation, $left, $right, $derivation), $derivation->context->columnNames);
    }

    /**
     * Combines the outputs of the operands.
     *
     * @return list<Field|OpenStar>
     */
    public function combined(SetOperation $operation, QueryFact $left, QueryFact $right, Derivation $derivation): array
    {
        $first = $left->fields();
        $second = $right->fields();
        if ($first === null || $second === null) {
            return [new OpenStar([...$left->shape->missing, ...$right->shape->missing])];
        }
        if (count($first) !== count($second)) {
            $derivation->report(new ArityMismatch(ArityRule::SetOperation, $operation->operator->value, count($first), count($second)));
        }
        $unknown = new UnknownOutputs();
        $unification = new Unification();
        $items = [];
        foreach ($first as $position => $field) {
            $other = $position < count($second) ? $second->at($position) : null;
            $type = $other === null ? $field->type : $unification->resolve($derivation->context, [$unknown->raw($field), $unknown->raw($other)], $operation->operator->value);
            if ($type instanceof Invalid) {
                $derivation->report($type->cause);
            }
            $nullability = $other === null ? $field->nullability : $unification->nullability([$field->nullability, $other->nullability]);
            $items[] = new Field($position, new OutputSlot($field->name, $type, $nullability, null, $field->slot));
        }

        return $items;
    }

    /**
     * Answers the environment of the second operand: the enclosing one, with the recursive common table this operation computes bound to the first operand.
     */
    public function rebound(SetOperation $operation, QueryFact $anchor, Environment $outer): Environment
    {
        $bindings = [];
        $changed = false;
        foreach ($outer->commonTables as $binding) {
            $definition = $binding->definition;
            if ($definition instanceof RecursiveDefinition && (new Carriers())->core($definition->recursiveQuery()) === $operation) {
                $slots = [];
                foreach ($anchor->fields() ?? [] as $position => $field) {
                    $slots[] = new OutputSlot($definition->recursiveColumns()[$position] ?? $field->name, $field->type, Nullability::Nullable, null, $field->slot);
                }
                $binding = new CommonBinding($binding->name, $definition, new RowShape($slots, $anchor->shape->missing));
                $changed = true;
            }
            $bindings[] = $binding;
        }

        return $changed ? new Environment($outer->context, $outer->outer, $outer->relations, $bindings, $outer->aliases) : $outer;
    }
}
