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
 * rule is one SpatialProblem; the server stops at the first. The problems are
 * reported in the observed order: integer token validity, then line feeds,
 * organization integer validity and repeated definitions in written order;
 * identifier range, then NAME, DEFINITION, ORGANIZATION and DESCRIPTION in turn,
 * each missing, blank, holding a control character, too long, then its
 * identifier out of range (verified on a live 8.4 server). Terminates: five
 * passes over the attributes.
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
     * Finds a declaration contradiction without a catalog, profile or execution state.
     *
     * @param list<SpatialAttribute> $attributes The complete written attribute list
     */
    public function declaration(Numeral $srid, array $attributes): ?SpatialProblem
    {
        $identifier = $this->repeated($attributes) ?? $this->identifier($srid);
        if ($identifier !== null) {
            return $identifier;
        }
        foreach ([SpatialAttributeKind::Name, SpatialAttributeKind::Definition, SpatialAttributeKind::Organization, SpatialAttributeKind::Description] as $kind) {
            $matching = array_filter($attributes, static fn (SpatialAttribute $attribute): bool => $attribute->kind === $kind);
            if ($matching === [] && ($kind === SpatialAttributeKind::Name || $kind === SpatialAttributeKind::Definition)) {
                return new SpatialProblem(SpatialRule::MissingAttribute, $kind);
            }
            foreach ($matching as $attribute) {
                $problems = $this->valueProblems($attribute);
                if ($problems !== []) {
                    return $problems[0];
                }
            }
        }
        return null;
    }

    /**
     * Answers the first repeated role in written attribute order.
     *
     * @param list<SpatialAttribute> $attributes The complete written attributes
     */
    public function repeated(array $attributes): ?SpatialProblem
    {
        $seen = [];
        foreach ($attributes as $attribute) {
            if (isset($seen[$attribute->kind->value])) {
                return new SpatialProblem(SpatialRule::RepeatedAttribute, $attribute->kind);
            }
            $seen[$attribute->kind->value] = true;
        }
        return null;
    }

    /**
     * Identifies a system identifier outside the modifiable unsigned 32-bit domain.
     */
    public function identifier(Numeral $srid): ?SpatialProblem
    {
        $magnitudes = new Magnitudes();
        if (!$magnitudes->atMost($srid, '4294967295')) {
            return new SpatialProblem(SpatialRule::IdentifierOutOfRange);
        }
        return $magnitudes->atMost($srid, '0') ? new SpatialProblem(SpatialRule::IdentifierZero) : null;
    }

    /**
     * Reports literal and duplicate problems before identifier and attribute constraints.
     *
     * @param list<SpatialAttribute> $attributes The attributes in written order
     */
    public function create(Derivation $derivation, Numeral $srid, array $attributes): void
    {
        if (!(new Magnitudes())->integer($derivation, $srid)) {
            return;
        }
        $this->definitions($derivation, $attributes);
        $this->srid($derivation, $srid);
        $this->attributes($derivation, $attributes);
    }

    /**
     * Reports an identifier out of range or zero.
     */
    public function srid(Derivation $derivation, Numeral $srid): void
    {
        $magnitudes = new Magnitudes();
        if (!$magnitudes->integer($derivation, $srid)) {
            return;
        }
        $problem = $this->identifier($srid);
        if ($problem !== null) {
            $derivation->report($problem);
        }
    }

    /**
     * Reports the problems of the attributes.
     *
     * @param list<SpatialAttribute> $attributes
     */
    public function definitions(Derivation $derivation, array $attributes): void
    {
        $seen = [];
        foreach ($attributes as $attribute) {
            $decoded = (new \SqlSemantics\Platform\MySql\Rules\Text\NameEncoding())->decode($attribute->value->value, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::of($derivation->context)->client);
            if (preg_match('/[\xF0-\xF4][\x80-\xBF]{3}/', $decoded) === 1) {
                $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\NameConversion(new \SqlSemantics\Statement\Identifier\Name($decoded)), $attribute);
            }
            if (str_contains($attribute->value->value, "\n")) {
                $derivation->report(new SpatialProblem(SpatialRule::LineFeed, $attribute->kind, $attribute->value->value));
            }
            if ($attribute->identifier !== null) {
                (new Magnitudes())->integer($derivation, $attribute->identifier);
            }
            if (isset($seen[$attribute->kind->value])) {
                $problem = new SpatialProblem(SpatialRule::RepeatedAttribute, $attribute->kind);
                $derivation->report($problem);
                $derivation->warn(new \SqlSemantics\Platform\MySql\Statement\Notice\ParseFailure($problem, true), $attribute);
            }
            $seen[$attribute->kind->value] = true;
        }
    }

    /**
     * Reports missing and invalid attribute values after their definitions have been checked.
     *
     * @param list<SpatialAttribute> $attributes The attributes in written order
     */
    public function attributes(Derivation $derivation, array $attributes): void
    {
        $seen = array_fill_keys(array_map(static fn (SpatialAttribute $attribute): string => $attribute->kind->value, $attributes), true);
        foreach ([SpatialAttributeKind::Name, SpatialAttributeKind::Definition, SpatialAttributeKind::Organization, SpatialAttributeKind::Description] as $kind) {
            if (!isset($seen[$kind->value]) && ($kind === SpatialAttributeKind::Name || $kind === SpatialAttributeKind::Definition)) {
                $derivation->report(new SpatialProblem(SpatialRule::MissingAttribute, $kind));
            }
            foreach ($attributes as $attribute) {
                if ($attribute->kind === $kind) {
                    $this->attribute($derivation, $attribute);
                }
            }
        }
    }

    /**
     * Reports the problems of one attribute.
     */
    public function attribute(Derivation $derivation, SpatialAttribute $attribute): void
    {
        foreach ($this->valueProblems($attribute) as $problem) {
            $derivation->report($problem);
        }
    }

    /**
     * Checks one attribute's context-independent value constraints in diagnostic order.
     *
     * @return list<SpatialProblem>
     */
    public function valueProblems(SpatialAttribute $attribute): array
    {
        $problems = [];
        $value = $attribute->value->value;
        $blank = $value === '' || preg_match('/\A[ \t\n\v\f\r]|[ \t\n\v\f\r]\z/', $value) === 1;
        if ($blank && $attribute->kind === SpatialAttributeKind::Name) {
            $problems[] = new SpatialProblem(SpatialRule::BlankName, $attribute->kind);
        }
        if ($blank && $attribute->kind === SpatialAttributeKind::Organization) {
            $problems[] = new SpatialProblem(SpatialRule::BlankOrganization, $attribute->kind);
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            $problems[] = new SpatialProblem(SpatialRule::ControlCharacter, $attribute->kind);
        }
        if (strlen($value) - (int) preg_match_all('/[\x80-\xBF]/', $value) > self::LIMITS[$attribute->kind->value]) {
            $problems[] = new SpatialProblem(SpatialRule::TooLong, $attribute->kind);
        }
        $magnitudes = new Magnitudes();
        if ($attribute->identifier !== null && $magnitudes->integral($attribute->identifier) && !$magnitudes->atMost($attribute->identifier, '4294967295')) {
            $problems[] = new SpatialProblem(SpatialRule::IdentifierOutOfRange, $attribute->kind);
        }
        return $problems;
    }
}
