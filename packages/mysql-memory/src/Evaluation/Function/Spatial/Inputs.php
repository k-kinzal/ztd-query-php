<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Axes;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use MySqlMemory\Value\Spatial\WkbInput;
use MySqlMemory\Value\Spatial\WktInput;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Constructs geometry values from WKT or WKB with a reference identifier and axis order.
 *
 * NULL text short-circuits later operands. Reference validation precedes options
 * and geometry validation. WKB validates its SRID before testing NULL input.
 * Legacy aliases ignore subtype restrictions.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-wkt-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Inputs
{
    /**
     * @return list<Routine> The text and binary constructors and their historical aliases
     */
    public function routines(): array
    {
        $routines = [new Routine('ST_GEOMCOLLFROMTXT', 1, 3, fn (Frame $f, array $a): ?string => $this->value($f, $a, 'st_geomcollfromtxt', 7))];
        foreach (['GEOM' => 0, 'GEOMETRY' => 0, 'POINT' => 1, 'LINE' => 2, 'LINESTRING' => 2, 'POLY' => 3, 'POLYGON' => 3, 'MPOINT' => 4, 'MULTIPOINT' => 4, 'MLINE' => 5, 'MULTILINESTRING' => 5, 'MPOLY' => 6, 'MULTIPOLYGON' => 6, 'GEOMCOLL' => 7, 'GEOMETRYCOLLECTION' => 7] as $type => $expected) {
            foreach (['', 'ST_'] as $prefix) {
                foreach (['TEXT', 'WKB'] as $format) {
                    $name = $prefix . $type . 'FROM' . $format;
                    $routines[] = new Routine($name, 1, 3, fn (Frame $f, array $a): ?string => $this->value($f, $a, strtolower($name), $expected, $format === 'WKB'));
                }
            }
        }
        return $routines;
    }

    /**
     * @param list<Evaluable> $arguments Text, optional SRID and axis options
     */
    public function value(Frame $frame, array $arguments, string $function, int $expected, bool $binary = false): ?string
    {
        $release = $frame->context->modes->release;
        $legacy = $release === GrammarRelease::MySql5651;
        $modern = !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $function = $modern ? $function : ($binary ? 'st_geometryfromwkb' : 'st_geometryfromtext');
        $parameters = $binary ? $this->parameters($frame, $arguments, $function, $modern, false) : null;
        if ($binary && $parameters === null) {
            return null;
        }
        $raw = $arguments[0]->evaluate($frame);
        if ($raw === null) {
            return null;
        }
        $parameters ??= $this->parameters($frame, $arguments, $function, $modern, $legacy);
        if ($parameters === null) {
            return null;
        }
        [$srid, $definition, $order] = $parameters;
        $latitudeFirst = Axes::latitudeFirst($definition);
        $bytes = (string) Convert::toText($raw, $arguments[0]->domain());
        $geometry = $this->read($bytes, $arguments[0]->domain()->field, $release, $function, $expected, $binary);
        if ($geometry === null) {
            return null;
        }
        if ($binary && $release === GrammarRelease::MySql5744 && $arguments[0]->domain()->field === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Geometry) {
            $this->warning($frame, count($arguments) > 1);
        }
        $geometry = $latitudeFirst !== null && ($order ?? $latitudeFirst) ? Axes::swap($geometry) : $geometry;
        if ($latitudeFirst !== null) {
            $this->coordinates($geometry, Axes::angularUnit($definition), $function);
        }
        return Wkb::write(new Geometry($geometry->type, $geometry->points, $geometry->children, $srid));
    }

    /**
     * Resolves reference and option operands before reading binary payloads, after non-NULL text.
     *
     * @param list<Evaluable> $arguments Payload, optional reference and axis options
     * @return array{int, string, ?bool}|null Reference identifier, definition and explicit axis order
     */
    public function parameters(Frame $frame, array $arguments, string $function, bool $modern, bool $nullAsZero): ?array
    {
        $srid = $this->identifier($frame, $arguments[1] ?? null, $function, $modern);
        if ($srid === null && !$nullAsZero) {
            return null;
        }
        $srid ??= 0;
        $definition = $modern ? $this->reference($frame, $srid) : '';
        $order = null;
        if (isset($arguments[2])) {
            $option = $arguments[2]->evaluate($frame);
            if ($option === null) {
                return null;
            }
            $order = AxisOptions::parse((string) Convert::toText($option, $arguments[2]->domain()), $function);
        }
        return [$srid, $definition, $order];
    }

    /**
     * Warns when MySQL 5.7 receives a non-NULL geometry-typed value as WKB.
     */
    public function warning(Frame $frame, bool $explicit): void
    {
        $code = $explicit ? DataError::GeometryWkbWithSridDeprecated : DataError::GeometryWkbDeprecated;
        $frame->context->diagnostics->warning($code, $code->message());
    }

    /**
     * Decodes the format and enforces the constructor's release-specific subtype contract.
     */
    public function read(string $bytes, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field $field, GrammarRelease $release, string $function, int $expected, bool $binary): ?Geometry
    {
        $legacy = $release === GrammarRelease::MySql5651;
        $modern = !in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        $geometry = $binary ? (new WkbInput())->read($bytes, $legacy, !$modern && $field === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::Geometry) : (new WktInput($bytes, $legacy, $modern))->read();
        if ($geometry === null) {
            return $legacy ? null : throw DataError::GisInvalidData->error($function);
        }
        if ($modern && $expected !== 0 && ($expected === 7 ? $geometry->type < 4 : $geometry->type !== $expected)) {
            throw DataError::UnexpectedGeometryType->error($binary ? 'WKB' : 'WKT', $geometry->name(), $function);
        }
        return $geometry;
    }

    /**
     * Converts SRIDs using the semantic operand type, including decimal rounding.
     */
    public function identifier(Frame $frame, ?Evaluable $argument, string $function, bool $modern): ?int
    {
        if ($argument === null) {
            return 0;
        }
        $value = $argument->evaluate($frame);
        $domain = $argument->domain();
        $srid = $value !== null && $domain->kind === \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind::Decimal
            ? Convert::decimalInteger((string) $value, $frame->context, true)
            : Convert::toInteger($value, $domain, $frame->context, true);
        if ($modern && $srid !== null && ($srid < 0 || $srid > 4294967295)) {
            throw DataError::DataOutOfRange->error('SRID', $function);
        }
        return $srid === null ? null : $srid & 4294967295;
    }

    /**
     * Resolves the current reference definition for each evaluation.
     */
    public function reference(Frame $frame, int $srid): string
    {
        $catalog = $frame->context->variables->instance->registry->spatialCatalog;
        if ($srid !== 0 && $catalog->name($srid) === null) {
            throw SchemaError::SrsNotFound->error($srid);
        }
        return $catalog->attributes($srid)[2];
    }

    /**
     * Checks geographic ordinates in their declared angular units after axis conversion.
     */
    public function coordinates(Geometry $geometry, float $unit, string $function): void
    {
        $longitude = M_PI / $unit;
        $latitude = M_PI_2 / $unit;
        foreach ($geometry->points as [$x, $y]) {
            if ($x <= -$longitude || $x > $longitude) {
                throw DataError::LongitudeOutOfRange->error(strtolower(sprintf('%.6f', $x)), $function, sprintf('%.6f', -$longitude), sprintf('%.6f', $longitude));
            }
            if ($y < -$latitude || $y > $latitude) {
                throw DataError::LatitudeOutOfRange->error(strtolower(sprintf('%.6f', $y)), $function, sprintf('%.6f', -$latitude), sprintf('%.6f', $latitude));
            }
        }
        foreach ($geometry->children as $child) {
            $this->coordinates($child, $unit, $function);
        }
    }
}
