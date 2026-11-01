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
 * Geometry type names use the connection collation; the maximum is 20 characters in MySQL 5.x
 * and 15 in later releases. Legacy emptiness predicates use a full-width integer result.
 * Verified through SQL result metadata and COLLATION on MySQL 5.6.51, 5.7.44 and 8.4.7.
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
        $legacy = static fn (Invocation $call): bool => in_array($call->derivation->context->profile->grammar, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
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
            'ST_SWAPXY' => $geometry,
            'ST_GEOMCOLLFROMTXT' => $geometry,
            'ST_NUMINTERIORRING' => $integer,
            'CONTAINS' => static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, 21),
        ];
        foreach (['X', 'Y', 'ST_X', 'ST_Y', 'ST_LONGITUDE', 'ST_LATITUDE'] as $name) {
            $rules[$name] = static fn (Invocation $call): Domain => count($call->domains) === 1 ? Domain::double(23) : $geometry($call);
        }
        foreach (['', 'ST_'] as $prefix) {
            foreach (['GEOM', 'GEOMETRY', 'POINT', 'LINE', 'LINESTRING', 'POLY', 'POLYGON', 'MPOINT', 'MULTIPOINT', 'MLINE', 'MULTILINESTRING', 'MPOLY', 'MULTIPOLYGON', 'GEOMCOLL', 'GEOMETRYCOLLECTION'] as $type) {
                $rules[$prefix . $type . 'FROMTEXT'] = $geometry;
                $rules[$prefix . $type . 'FROMWKB'] = $geometry;
            }
            foreach (['ASTEXT', 'ASWKT'] as $name) {
                $rules[$prefix . $name] = static fn (Invocation $call): Domain => Domain::string(16777216 * ($legacy($call) ? 1 : $call->settings->connection->charset->maxLength), $call->settings->connection, Field::LongBlob, Coercibility::Coercible);
            }
            foreach (['ASBINARY', 'ASWKB'] as $name) {
                $rules[$prefix . $name] = static fn (Invocation $call): Domain => new Domain(Kind::String, $legacy($call) ? Field::Blob : Field::LongBlob, 4294967295, $legacy($call) ? 0 : Domain::NOT_FIXED, false, Collation::binary(), [], Coercibility::Coercible);
            }
            foreach (['GEOMETRYN', 'POINTN', 'STARTPOINT', 'ENDPOINT', 'EXTERIORRING', 'INTERIORRINGN'] as $name) {
                $rules[$prefix . $name] = $geometry;
            }
            $rules[$prefix . 'NUMPOINTS'] = $integer;
            $rules[$prefix . 'NUMINTERIORRINGS'] = $integer;
            $rules[$prefix . 'ISCLOSED'] = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $legacy($call) ? 21 : 1);
            $rules[$prefix . 'DIMENSION'] = $integer;
            $rules[$prefix . 'NUMGEOMETRIES'] = $integer;
            $rules[$prefix . 'ISEMPTY'] = static fn (Invocation $call): Domain => Domain::integer(Field::LongLong, $legacy($call) ? 21 : 1);
            $rules[$prefix . 'GEOMETRYTYPE'] = static fn (Invocation $call): Domain => Domain::string($legacy($call) ? 20 : 15, $call->settings->connection, Field::VarString, Coercibility::Coercible);
        }

        return $rules;
    }
}
