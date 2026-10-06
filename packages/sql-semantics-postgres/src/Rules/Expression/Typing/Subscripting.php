<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\FieldSelection;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\IndirectionStep;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\ImproperStar;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\MissingField;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotComposite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NotSubscriptable;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\AllFields;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Step\Slice;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Types the steps of an indirection applied to a value.
 *
 * Rule: PG-SUBSCRIPT-001. A run of subscripts on an array yields its
 * element type, and the array type itself when a step of the run is a
 * slice; a subscript of `jsonb` yields `jsonb` and of `point` a `double
 * precision` coordinate; another catalog type has no subscripting. A field
 * selection reads the field of a composite value; a field it lacks and a
 * value that is not composite are reported. `.*` ends an indirection and
 * stands for the whole value; written before another step it is improper,
 * as it is after a dotted relation name. A value of a type that depends on
 * declarations makes every step depend on them. A subscript can be out of
 * range, so its result can be NULL; a field is NULL when its value or the
 * composite is. Termination: one pass over the steps.
 * Source: https://www.postgresql.org/docs/17/arrays.html#ARRAYS-ACCESSING,
 * https://www.postgresql.org/docs/17/datatype-json.html#JSONB-SUBSCRIPTING, https://www.postgresql.org/docs/17/rowtypes.html#ROWTYPES-ACCESSING. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Subscripting
{
    /**
     * Applies the steps to the facts of the base value.
     *
     * @param non-empty-list<IndirectionStep> $steps
     * @param bool $star Whether the base is a dotted name ending in `.*`
     */
    public function apply(Derivation $derivation, ScalarFact $base, array $steps, bool $star): ScalarFact
    {
        $last = count($steps) - 1;
        foreach ($steps as $index => $step) {
            if ($star || ($step instanceof AllFields && $index !== $last)) {
                return $this->problem($derivation, new ImproperStar());
            }
        }
        $type = $base->type;
        $nullability = $base->nullability;
        for ($index = 0; $index <= $last && $type instanceof Known; $index++) {
            $step = $steps[$index];
            if ($step instanceof AllFields) {
                continue;
            }
            if ($step instanceof FieldSelection) {
                $field = $this->field($derivation, $type, $step);
                if ($field instanceof Invalid) {
                    return new ScalarFact($field, Nullability::Dependent);
                }
                [$type, $fieldNullability] = $field;
                $nullability = $nullability->propagate($fieldNullability);
                continue;
            }
            $run = [$step];
            while ($index < $last && !$steps[$index + 1] instanceof FieldSelection && !$steps[$index + 1] instanceof AllFields) {
                $run[] = $steps[++$index];
            }
            $type = $this->subscript($derivation, $type, $run);
            $nullability = Nullability::Nullable;
        }

        return new ScalarFact($type, $type instanceof Invalid ? Nullability::Dependent : $nullability);
    }

    /**
     * Types a run of subscripts and slices applied to a value of a known type.
     *
     * @param non-empty-list<IndirectionStep> $run
     */
    public function subscript(Derivation $derivation, Known $type, array $run): TypeFact
    {
        $sliced = false;
        foreach ($run as $step) {
            $sliced = $sliced || $step instanceof Slice;
        }
        if ($type->descriptor instanceof ArrayOf) {
            return $sliced ? $type : new Known($type->descriptor->element);
        }
        $base = (new Categories())->builtin($type);
        if ($base === Builtin::Jsonb && !$sliced) {
            return $type;
        }
        if ($base === Builtin::Point && !$sliced && count($run) === 1) {
            return new Known(Builtin::Float8);
        }
        $problem = new NotSubscriptable($type->descriptor->name());
        $derivation->report($problem);

        return new Invalid($problem);
    }

    /**
     * Reads a field of a value of a known type.
     *
     * @return array{TypeFact, Nullability}|Invalid
     */
    public function field(Derivation $derivation, Known $type, FieldSelection $step): array|Invalid
    {
        if (!$type->descriptor instanceof Composite) {
            $problem = new NotComposite($step->name->value, $type->descriptor->name());
            $derivation->report($problem);

            return new Invalid($problem);
        }
        foreach ($type->descriptor->fields as $slot) {
            if ($slot->name !== null && $derivation->context->columnNames->equal($slot->name->value, $step->name->value)) {
                return [$slot->type, $slot->nullability];
            }
        }
        $problem = new MissingField($step->name->value, $type->descriptor->name());
        $derivation->report($problem);

        return new Invalid($problem);
    }

    /**
     * Reports a problem of the indirection and answers its invalid fact.
     */
    public function problem(Derivation $derivation, Diagnostic $problem): ScalarFact
    {
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }
}
