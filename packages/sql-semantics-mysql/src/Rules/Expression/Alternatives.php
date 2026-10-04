<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Statement\Type\Choice;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeDescriptor;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Lifts a type rule over the alternatives of its operand types.
 *
 * Rule: MYSQL-TYPE-ALTERNATIVES-001. An operand whose request is invalid
 * makes the result invalid with the same cause; otherwise an operand that
 * depends on missing inputs makes the result depend on all of them. A known
 * type is one alternative, a choice is each of its alternatives, and the
 * type of a bare NULL is the alternative null. The results of a rule over
 * the alternatives are one known type when they agree and a choice
 * otherwise. Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class Alternatives
{
    /**
     * Answers the type fact that decides the result without a rule: the first invalid operand, or the dependence on every missing input.
     *
     * @param list<TypeFact> $types
     */
    public function blocking(array $types): ?TypeFact
    {
        $missing = [];
        foreach ($types as $type) {
            if ($type instanceof Invalid) {
                return $type;
            }
            if ($type instanceof Dependent) {
                array_push($missing, ...$type->missing);
            }
        }

        return $missing === [] ? null : new Dependent($missing);
    }

    /**
     * Answers the alternatives of a type that does not block: its descriptors, or null for a bare NULL.
     *
     * @return list<TypeDescriptor|null>
     */
    public function of(TypeFact $type): array
    {
        if ($type instanceof Known) {
            return [$type->descriptor];
        }

        return $type instanceof Choice ? $type->alternatives : [null];
    }

    /**
     * Answers the type fact of a list of possible result types.
     *
     * @param list<TypeDescriptor> $types At least one
     */
    public function known(array $types): TypeFact
    {
        $distinct = [];
        foreach ($types as $type) {
            $distinct[$this->key($type)] ??= $type;
        }
        $list = array_values($distinct);
        if ($list === []) {
            return new NullOnly();
        }

        return count($list) === 1 ? new Known($list[0]) : new Choice($list);
    }

    /**
     * Answers the key two result types share when they are the same type.
     */
    public function key(TypeDescriptor $type): string
    {
        return $type::class . ':' . $type->name() . ($type instanceof Integral && $type->unsigned() ? ':unsigned' : '');
    }
}
