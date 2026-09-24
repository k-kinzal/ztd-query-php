<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Routine\Declaration;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Scalar\Value\LiteralKind;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Rules of routine bodies and the languages that interpret them.
 * @visibility SqlSemantics
 */
final class BodyInvariant
{
    /**
     * A definition string is a PostgreSQL string constant.
     * @throws InvalidStructure
     */
    public static function text(Literal $value): void
    {
        if ($value->literalKind !== LiteralKind::Text || $value->type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A routine definition is a PostgreSQL string constant.');
        }
    }

    /**
     * An inline body requires language SQL, and an object file with a link symbol requires language C.
     * @throws InvalidStructure
     */
    public static function language(string $language, RoutineBody $body): void
    {
        if ($language === '') {
            throw new InvalidStructure('A routine language has a name.');
        }
        if (($body instanceof ReturnBody || $body instanceof AtomicBody) && $language !== 'sql') {
            throw new InvalidStructure('An inline SQL body requires language SQL.');
        }
        if ($body instanceof LinkedBody && $language !== 'c') {
            throw new InvalidStructure('Only language C takes an object file and a link symbol.');
        }
    }
}
