<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Axes;
use MySqlMemory\Value\Spatial\Wkb;
use MySqlMemory\Value\Spatial\Wkt;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Converts internal geometry values to WKT or WKB and exchanges coordinate axes.
 *
 * Undefined reference identifiers warn for output conversion but fail for ST_SWAPXY.
 * Options are evaluated after the geometry is read; a NULL geometry does not read them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/gis-format-conversion-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Formats
{
    /**
     * @return list<Routine> The output conversions, aliases and axis exchange
     */
    public function routines(): array
    {
        $routines = [new Routine('ST_SWAPXY', 1, 1, fn (Frame $f, array $a): ?string => $this->value($f, $a, 'swap'))];
        foreach (['ASTEXT', 'ASWKT', 'ASBINARY', 'ASWKB'] as $name) {
            foreach (['', 'ST_'] as $prefix) {
                $routines[] = new Routine($prefix . $name, 1, 2, fn (Frame $f, array $a): ?string => $this->value($f, $a, in_array($name, ['ASTEXT', 'ASWKT'], true) ? 'text' : 'binary'));
            }
        }

        return $routines;
    }

    /**
     * @param list<Evaluable> $arguments Geometry and optional axis order
     */
    public function value(Frame $frame, array $arguments, string $format): ?string
    {
        $raw = FailedGeometry::read($arguments[0], $frame, match ($format) {
            'text' => 'st_astext', 'binary' => 'st_aswkb', default => 'st_swapxy',
        });
        if ($raw === null) {
            return null;
        }
        $bytes = (string) Convert::toText($raw, $arguments[0]->domain());
        $release = $frame->context->modes->release;
        if ($release === GrammarRelease::MySql5651) {
            return $this->legacy($bytes, $format);
        }
        $function = match ($format) {
            'text' => 'st_astext', 'binary' => 'st_aswkb', default => 'st_swapxy'
        };
        $geometry = (new Properties())->geometry($bytes, $function);
        $modern = $release !== GrammarRelease::MySql5744;
        $latitudeFirst = $modern ? $this->reference($frame, $geometry->srid, $format === 'swap') : null;
        $order = null;
        if (isset($arguments[1])) {
            $option = $arguments[1]->evaluate($frame);
            if ($option === null) {
                return null;
            }
            $order = AxisOptions::parse((string) Convert::toText($option, $arguments[1]->domain()), $function);
        }
        $swap = $format === 'swap' || ($latitudeFirst !== null && ($order ?? $latitudeFirst));
        $geometry = $swap ? Axes::swap($geometry) : $geometry;

        return match ($format) {
            'text' => Wkt::write($geometry, emptyKeyword: $modern),
            'binary' => Wkb::body($geometry),
            default => Wkb::write($geometry),
        };
    }

    /**
     * Reads the reference catalog while retaining the output functions' warning-only behavior.
     */
    public function reference(Frame $frame, int $srid, bool $required): ?bool
    {
        $catalog = $frame->context->variables->instance->registry->spatialCatalog;
        if ($srid !== 0 && $catalog->name($srid) === null) {
            if ($required) {
                throw SchemaError::SrsNotFound->error($srid);
            }
            $error = SchemaError::SrsAxisOrderUnknown;
            $frame->context->diagnostics->warning($error, $error->message($srid));
        }

        return Axes::latitudeFirst($catalog->attributes($srid)[2]);
    }

    /**
     * Reads a legacy header before exporting, preserving trailing bytes only in binary output.
     */
    public function legacy(string $bytes, string $format): ?string
    {
        if ((new Srid())->legacy($bytes) === null) {
            return null;
        }
        if ($format === 'binary') {
            return substr($bytes, 4);
        }
        $offset = 4;
        $geometry = Wkb::geometry(substr_replace($bytes, "\1", 4, 1), $offset, 0, true);

        return $geometry === null || !(new Properties())->readable($geometry) ? null : Wkt::write($geometry, true, false);
    }
}
