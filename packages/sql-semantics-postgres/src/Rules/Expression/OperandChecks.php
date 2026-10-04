<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Categories;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\Composite;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\NoCollation;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\OperandMismatch;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Checks the operand types that a construct accepts, and combines the NULL facts of operands.
 *
 * Rule: PG-OPERAND-CHECK-001. A boolean position (AND, OR, NOT, IS TRUE …)
 * accepts `boolean` and the pseudo-type `unknown`, which becomes boolean;
 * IS DOCUMENT accepts `xml`, IS NORMALIZED text, IS JSON text, `json`,
 * `jsonb` and `bytea`, unknown-typed constants included; COLLATE accepts
 * the character string types and arrays of them. Another catalog type is
 * reported and makes the construct invalid; a type that depends on
 * declarations or is invalid is not checked here. A strict construct can be
 * NULL when an operand can; NULL itself can. Termination: constant work per
 * operand. Source: https://www.postgresql.org/docs/17/functions-logical.html,
 * https://www.postgresql.org/docs/17/functions-xml.html#FUNCTIONS-XML-PREDICATES,
 * https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-MISC,
 * https://www.postgresql.org/docs/17/collation.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class OperandChecks
{
    /**
     * Checks a boolean operand; answers the invalid fact of a mismatch, or null when the operand is acceptable or unchecked.
     */
    public function boolean(Derivation $derivation, TypeFact $type, string $construct): ?Invalid
    {
        $base = (new Categories())->builtin($type);
        if (!$type instanceof Known || $base === Builtin::Bool || $base === Builtin::Unknown) {
            return null;
        }
        $problem = new OperandMismatch($construct, 'boolean', $type->descriptor->name());
        $derivation->report($problem);

        return new Invalid($problem);
    }

    /**
     * Checks an operand against the catalog types a predicate accepts; answers `boolean` or the invalid fact of a mismatch.
     *
     * @param list<Builtin> $accepted
     */
    public function accepted(Derivation $derivation, TypeFact $type, array $accepted, string $construct): TypeFact
    {
        $base = (new Categories())->builtin($type);
        if (!$type instanceof Known || $base === Builtin::Unknown || in_array($base, $accepted, true)) {
            return $type instanceof Invalid ? $type : new Known(Builtin::Bool);
        }
        $problem = new OperandMismatch($construct, $accepted[0]->name(), $type->descriptor->name());
        $derivation->report($problem);

        return new Invalid($problem);
    }

    /**
     * Checks that the type of a collated value has collations; answers the type, or the invalid fact of a mismatch.
     */
    public function collatable(Derivation $derivation, TypeFact $type): TypeFact
    {
        if (!$type instanceof Known) {
            return $type;
        }
        $element = $type->descriptor instanceof ArrayOf ? new Known($type->descriptor->element) : $type;
        $base = (new Categories())->builtin($element);
        if ($base === null || $base === Builtin::Unknown || (new Categories())->textual($base)) {
            return $type;
        }
        $problem = new NoCollation($type->descriptor->name());
        $derivation->report($problem);

        return new Invalid($problem);
    }

    /**
     * Combines the NULL facts of the operands of a strict construct.
     *
     * @param list<ScalarFact> $operands
     */
    public function nullability(array $operands): Nullability
    {
        $result = Nullability::NotNull;
        foreach ($operands as $operand) {
            $result = $result->propagate($operand->type instanceof NullOnly ? Nullability::Nullable : $operand->nullability);
        }

        return $result;
    }

    /**
     * Combines the NULL facts of the fields of rows, or of the values themselves when they are not rows.
     *
     * @param list<ScalarFact> $operands
     */
    public function fields(array $operands): Nullability
    {
        $result = Nullability::NotNull;
        foreach ($operands as $operand) {
            if ($operand->type instanceof Known && $operand->type->descriptor instanceof Composite) {
                foreach ($operand->type->descriptor->fields as $field) {
                    $result = $result->propagate($field->nullability);
                }
                continue;
            }
            $result = $result->propagate($this->nullability([$operand]));
        }

        return $result;
    }
}
