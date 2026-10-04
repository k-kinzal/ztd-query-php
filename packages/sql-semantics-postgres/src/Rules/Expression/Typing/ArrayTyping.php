<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Expression\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Unification;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\ArrayItems;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\ArrayOf;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * Types an array constructor.
 *
 * Rule: PG-ARRAY-TYPING-001. Every value of every level is derived in
 * order. The element type is the common type of the values
 * (PG-UNIFICATION-001), unknown-typed values giving `text`; when the values
 * are themselves arrays, or the levels hold sub-arrays, the array is
 * multidimensional and keeps the element type, since PostgreSQL arrays of
 * any dimension share one type. A constructor without values has the element
 * type `unknown` until a cast gives it one. Termination: the levels are
 * walked with an explicit stack.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-SYNTAX-ARRAY-CONSTRUCTORS. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ArrayTyping
{
    /**
     * Derives the values of the items and answers the type of the array.
     */
    public function items(Derivation $derivation, Environment $environment, ArrayItems $items): TypeFact
    {
        $types = [];
        $pending = [$items];
        while ($pending !== []) {
            $level = array_shift($pending);
            foreach ($level->values as $value) {
                $types[] = $derivation->scalar($value, $environment)->type;
            }
            array_push($pending, ...$level->nested);
        }
        if ($types === []) {
            return new Known(new ArrayOf(Builtin::Unknown));
        }
        $element = (new Unification())->resolve($derivation->context, $types, 'ARRAY');
        if ($element instanceof Invalid && !in_array($element, $types, true)) {
            $derivation->report($element->cause);
        }
        if (!$element instanceof Known) {
            return $element;
        }

        return $element->descriptor instanceof ArrayOf ? $element : new Known(new ArrayOf($element->descriptor));
    }
}
