<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Spatial;

use Closure;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Typing\Builtin\Invocation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Resolves geometry constructors, reference identifiers and structural properties.
 *
 * Geometry type names use the connection collation; the maximum is 20 characters in 5.6
 * and 15 in later releases. Legacy emptiness predicates use a full-width integer result.
 * Verified through SQL result metadata and COLLATION on MySQL 5.6.51 and 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql\Rules\Typing
 */
final class SpatialResults
{
    /**
     * Answers the property result rules shared by current and legacy function names.
     *
     * @return array<string, Closure(Invocation): ?Domain>
     */
    public function rules(): array
    {
        $geometry = static fn (Invocation $call): Domain => new Domain(Kind::String, Field::Geometry, 4294967295, 0, false, Collation::binary(), [], Coercibility::Coercible);
        $integer = static fn (Invocation $call): Domain => new Domain(Kind::Integer, Field::LongLong, 10, 0, false, null, [], Coercibility::Numeric, storageField: Field::Long);
        $rules = [
            'ST_SRID' => static fn (Invocation $call): Domain => count($call->domains) === 1 ? $integer($call) : $geometry($call),
            'SRID' => $integer,
            'POINT' => $geometry,
            'LINESTRING' => $geometry,
            'POLYGON' => $geometry,
            'MULTIPOINT' => $geometry,
            'MULTILINESTRING' => $geometry,
            'MULTIPOLYGON' => $geometry,
            'GEOMETRYCOLLECTION' => $geometry,
            'GEOMCOLLECTION' => $geometry,
        ];
        foreach (['', 'ST_'] as $prefix) {
            $rules[$prefix . 'DIMENSION'] = $integer;
            $rules[$prefix . 'NUMGEOMETRIES'] = $integer;
            $rules[$prefix . 'ISEMPTY'] = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $call->derivation->context->profile->grammar === GrammarRelease::MySql5651 ? 21 : 1);
            $rules[$prefix . 'GEOMETRYTYPE'] = static fn (Invocation $call): Domain => Domain::string($call->derivation->context->profile->grammar === GrammarRelease::MySql5651 ? 20 : 15, $call->settings->connection, Field::VarString, Coercibility::Coercible);
        }

        return $rules;
    }
}
