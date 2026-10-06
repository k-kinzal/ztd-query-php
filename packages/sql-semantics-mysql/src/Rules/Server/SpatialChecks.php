<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Server;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttribute;
use SqlSemantics\Platform\MySql\Statement\Server\Spatial\SpatialAttributeKind;

/**
 * Checks a spatial reference system statement as the server does while it parses it.
 *
 * Rule: MYSQL-SRS-CHECK-001. Mirrors the `srs_attributes` actions and
 * PT_create_srs::make_cmd / PT_drop_srs::make_cmd of the server: the
 * identifier is 1 to 2^32-1 (gis::srid_t); each attribute is written at
 * most once; NAME and DEFINITION are mandatory; NAME and ORGANIZATION are
 * not empty and neither start nor end with whitespace; no attribute holds a
 * control character (0x00-0x1F, 0x7F); NAME holds at most 80 characters,
 * DEFINITION 4096, ORGANIZATION 256 and DESCRIPTION 2048, counted as UTF-8
 * characters; the organization's identifier is at most 2^32-1. Each broken
 * rule is one SpatialProblem; the server stops at the first. Terminates: one
 * pass over the attributes.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-spatial-reference-system.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SpatialChecks
{
    /**
     * The character limits of the attributes.
     */
    private const LIMITS = ['NAME' => 80, 'DEFINITION' => 4096, 'ORGANIZATION' => 256, 'DESCRIPTION' => 2048];

    /**
     * Reports an identifier out of range or zero.
     */
    public function srid(Derivation $derivation, Numeral $srid): void
    {
        $magnitudes = new Magnitudes();
        if (!$magnitudes->integer($derivation, $srid)) {
            return;
        }
        if (!$magnitudes->atMost($srid, '4294967295')) {
            $derivation->report(new SpatialProblem(SpatialRule::IdentifierOutOfRange));
        } elseif ($magnitudes->atMost($srid, '0')) {
            $derivation->report(new SpatialProblem(SpatialRule::IdentifierZero));
        }
    }

    /**
     * Reports the problems of the attributes.
     *
     * @param list<SpatialAttribute> $attributes
     */
    public function attributes(Derivation $derivation, array $attributes): void
    {
        $seen = [];
        foreach ($attributes as $attribute) {
            if (isset($seen[$attribute->kind->value])) {
                $derivation->report(new SpatialProblem(SpatialRule::RepeatedAttribute, $attribute->kind));
            }
            $seen[$attribute->kind->value] = true;
            $this->attribute($derivation, $attribute);
        }
        foreach ([SpatialAttributeKind::Name, SpatialAttributeKind::Definition] as $mandatory) {
            if (!isset($seen[$mandatory->value])) {
                $derivation->report(new SpatialProblem(SpatialRule::MissingAttribute, $mandatory));
            }
        }
    }

    /**
     * Reports the problems of one attribute.
     */
    public function attribute(Derivation $derivation, SpatialAttribute $attribute): void
    {
        $value = $attribute->value->value;
        $blank = $value === '' || preg_match('/\A[ \t\n\v\f\r]|[ \t\n\v\f\r]\z/', $value) === 1;
        if ($blank && $attribute->kind === SpatialAttributeKind::Name) {
            $derivation->report(new SpatialProblem(SpatialRule::BlankName, $attribute->kind));
        }
        if ($blank && $attribute->kind === SpatialAttributeKind::Organization) {
            $derivation->report(new SpatialProblem(SpatialRule::BlankOrganization, $attribute->kind));
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $derivation->report(new SpatialProblem(SpatialRule::ControlCharacter, $attribute->kind));
        }
        if (strlen($value) - (int) preg_match_all('/[\x80-\xBF]/', $value) > self::LIMITS[$attribute->kind->value]) {
            $derivation->report(new SpatialProblem(SpatialRule::TooLong, $attribute->kind));
        }
        $magnitudes = new Magnitudes();
        if ($attribute->identifier !== null && $magnitudes->integer($derivation, $attribute->identifier) && !$magnitudes->atMost($attribute->identifier, '4294967295')) {
            $derivation->report(new SpatialProblem(SpatialRule::IdentifierOutOfRange, $attribute->kind));
        }
    }
}
