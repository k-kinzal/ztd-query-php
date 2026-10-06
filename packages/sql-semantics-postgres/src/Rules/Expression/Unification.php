<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression;

use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing\Categories;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Problem\TypeConflict;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Resolves the common type of values that UNION, CASE, ARRAY, VALUES, IN and the conditional functions combine.
 *
 * Rule: PG-UNIFICATION-001. An invalid input makes the result invalid; an
 * input of a type that depends on missing declarations makes it depend on
 * them. Values of one type keep it, modifiers included. When every input is
 * of the pseudo-type `unknown` (a string constant or NULL), the result is
 * `text`. Otherwise unknown inputs are ignored; the other inputs must share
 * a category (PG-TYPE-CATEGORY-001), else the types cannot be matched. The
 * first input is the candidate; each later input replaces it when the
 * candidate converts to it implicitly but not back, unless the candidate is
 * the preferred type of the category. Every input must then convert to the
 * candidate implicitly. A type outside the category tables matches only
 * itself. Minimum precision: `Known` for the catalog types of the tables.
 * Termination: one pass over the inputs.
 * Source: https://www.postgresql.org/docs/17/typeconv-union-case.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Unification
{
    /**
     * Answers the common type of the inputs; a conflict is an `Invalid` fact the caller reports.
     *
     * @param list<TypeFact> $types The input types in order; at least one
     * @param string $construct The construct that combines them, for the conflict message
     */
    public function resolve(AnalysisContext $context, array $types, string $construct = 'UNION'): TypeFact
    {
        $missing = [];
        $known = [];
        foreach ($types as $type) {
            if ($type instanceof Invalid || $type instanceof Choice) {
                return $type;
            }
            if ($type instanceof Dependent) {
                array_push($missing, ...$type->missing);
            } elseif ($type instanceof Known && $type->descriptor !== Builtin::Unknown) {
                $known[] = $type;
            }
        }
        if ($missing !== []) {
            return new Dependent($this->distinct($missing));
        }
        if ($known === []) {
            return new Known(Builtin::Text);
        }

        return $this->candidate($known, $construct);
    }

    /**
     * Selects the candidate among inputs of known types.
     *
     * @param non-empty-list<Known> $types
     */
    public function candidate(array $types, string $construct): TypeFact
    {
        $first = $types[0];
        $same = true;
        foreach ($types as $type) {
            $same = $same && $type->descriptor->name() === $first->descriptor->name();
        }
        if ($same) {
            return $first;
        }
        $categories = new Categories();
        $candidate = $categories->builtin($first);
        foreach ($types as $type) {
            $next = $categories->builtin($type);
            if ($candidate === null || $next === null || $categories->category($candidate) !== $categories->category($next)) {
                return new Invalid(new TypeConflict($construct, $first->descriptor->name(), $type->descriptor->name()));
            }
            if (!$categories->preferred($candidate) && $categories->implicit($candidate, $next) && !$categories->implicit($next, $candidate)) {
                $candidate = $next;
            }
        }
        foreach ($types as $type) {
            $base = $categories->builtin($type);
            if ($base === null || !$categories->implicit($base, $candidate)) {
                return new Invalid(new TypeConflict($construct, $candidate->name(), $type->descriptor->name()));
            }
        }

        return new Known($candidate);
    }

    /**
     * Answers whether a value combined from inputs can be NULL: when any can; dependent when any is and none can.
     *
     * @param list<Nullability> $values
     */
    public function nullability(array $values): Nullability
    {
        $result = Nullability::NotNull;
        foreach ($values as $value) {
            $result = $result->propagate($value);
        }

        return $result;
    }

    /**
     * Removes repeated missing inputs, keeping the first of equal ones.
     *
     * @param non-empty-list<MissingInput> $missing
     *
     * @return non-empty-list<MissingInput>
     */
    public function distinct(array $missing): array
    {
        $result = [];
        foreach ($missing as $input) {
            $result[$input->describe()] ??= $input;
        }

        return array_values($result);
    }
}
