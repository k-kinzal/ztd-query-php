<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\OperandMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\RowArity;
use SqlSemantics\Platform\PostgreSql\Statement\Name\OperatorName;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Shape\Field;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Types the sublinks: a query, or an array, used inside an expression.
 *
 * Rule: PG-SUBLINK-001. A scalar subquery of one column has the column's
 * type; of several columns it is a row, which only a row comparison
 * accepts; of none it is reported. ARRAY(query) needs one column and gives
 * an array of its type, an array column giving the same array type. A
 * comparison with the rows of a query (IN, ANY, SOME, ALL) applies the
 * operator to the value and the one column, or field by field to a row and
 * as many columns; another number of columns is reported. A comparison with
 * the elements of an array applies the operator to the value and the
 * element type; a string constant on the right is read as an array of the
 * value's type, and another non-array type is reported. A query of an open
 * shape makes the result depend on its missing inputs.
 * Termination: one pass over the columns.
 * Source: https://www.postgresql.org/docs/17/functions-subquery.html,
 * https://www.postgresql.org/docs/17/functions-comparisons.html, https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-SCALAR-SUBQUERIES. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Sublinks
{
    /**
     * Types the value of a scalar subquery.
     */
    public function value(Derivation $derivation, QueryFact $fact, string $construct): TypeFact
    {
        $slots = $this->slots($fact);
        if ($slots instanceof Dependent) {
            return $slots;
        }
        if ($slots === []) {
            return $this->arity($derivation, $construct, 1, 0);
        }

        return count($slots) === 1 ? $slots[0]->type : new Known(new Composite($slots));
    }

    /**
     * Types the array an ARRAY subquery builds.
     */
    public function array(Derivation $derivation, QueryFact $fact): TypeFact
    {
        $slots = $this->slots($fact);
        if ($slots instanceof Dependent) {
            return $slots;
        }
        if (count($slots) !== 1) {
            return $this->arity($derivation, 'an ARRAY subquery', 1, count($slots));
        }
        $type = $slots[0]->type;
        if (!$type instanceof Known) {
            return $type;
        }

        return $type->descriptor instanceof ArrayOf ? $type : new Known(new ArrayOf($type->descriptor));
    }

    /**
     * Types the comparison of a value with the rows of a query.
     */
    public function compared(Derivation $derivation, TypeFact $operand, OperatorName|string $operator, QueryFact $fact): TypeFact
    {
        $slots = $this->slots($fact);
        if ($slots instanceof Dependent) {
            return $slots;
        }
        $typing = new OperatorTyping();
        $fields = $operand instanceof Known && $operand->descriptor instanceof Composite ? $operand->descriptor->fields : null;
        if ($fields === null) {
            return count($slots) === 1 ? $typing->named($derivation->context, $operator, $operand, $slots[0]->type) : $this->arity($derivation, 'a subquery comparison', 1, count($slots));
        }
        if (count($fields) !== count($slots)) {
            return $this->arity($derivation, 'a subquery comparison', count($fields), count($slots));
        }
        $type = new Known(Builtin::Bool);
        foreach ($fields as $position => $field) {
            $type = $typing->both($type, $typing->named($derivation->context, $operator, $field->type, $slots[$position]->type));
        }

        return $type;
    }

    /**
     * Types the comparison of a value with the elements of an array.
     */
    public function elements(Derivation $derivation, TypeFact $operand, OperatorName $operator, TypeFact $array): TypeFact
    {
        $typing = new OperatorTyping();
        if ($array instanceof Invalid || $array instanceof Dependent) {
            return $array;
        }
        if ($array instanceof NullOnly || ($array instanceof Known && (new Categories())->builtin($array) === Builtin::Unknown)) {
            return $typing->named($derivation->context, $operator, $operand, $operand);
        }
        if ($array instanceof Known && $array->descriptor instanceof ArrayOf) {
            return $typing->named($derivation->context, $operator, $operand, new Known($array->descriptor->element));
        }
        if (!$array instanceof Known) {
            return $array;
        }
        $problem = new OperandMismatch($operator->name->value . ' ANY/ALL (array)', 'array', $array->descriptor->name());
        $derivation->report($problem);

        return new Invalid($problem);
    }

    /**
     * Answers the output slots of a query, or its dependence on missing inputs when its shape is open.
     *
     * @return list<OutputSlot>|Dependent
     */
    public function slots(QueryFact $fact): array|Dependent
    {
        $fields = $fact->fields();
        if ($fields === null) {
            return new Dependent($fact->shape->missing);
        }
        $slots = [];
        foreach ($fact->projection as $field) {
            if ($field instanceof Field) {
                $slots[] = $field->slot;
            }
        }

        return $slots;
    }

    /**
     * Reports a wrong number of columns and answers its invalid fact.
     */
    public function arity(Derivation $derivation, string $construct, int $expected, int $actual): Invalid
    {
        $problem = new RowArity($construct, $expected, $actual);
        $derivation->report($problem);

        return new Invalid($problem);
    }
}
