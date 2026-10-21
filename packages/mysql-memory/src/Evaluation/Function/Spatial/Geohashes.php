<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Spatial;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Routine;
use MySqlMemory\Value\Spatial\Geohash;
use MySqlMemory\Value\Spatial\Geometry;
use MySqlMemory\Value\Spatial\Wkb;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Evaluates geohash encodings and coordinates after Semantics resolves argument types.
 *
 * Conversions precede NULL propagation. Modern point decoding validates the
 * numeric SRID before NULL propagation and its catalog entry after it. Geometry
 * encoding uses stored longitude/latitude order. Verified through native SQL.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/spatial-geohash-functions.html.
 *
 * @visibility MySqlMemory
 */
final class Geohashes
{
    /**
     * @return list<Routine> The encoder and three decoders
     */
    public function routines(): array
    {
        return [
            new Routine('ST_GEOHASH', 2, 3, $this->encode(...)),
            new Routine('ST_POINTFROMGEOHASH', 2, 2, $this->point(...)),
            new Routine('ST_LATFROMGEOHASH', 1, 1, fn (Frame $frame, array $arguments): ?float => $this->coordinate($frame, $arguments, 'ST_LATFROMGEOHASH', 1)),
            new Routine('ST_LONGFROMGEOHASH', 1, 1, fn (Frame $frame, array $arguments): ?float => $this->coordinate($frame, $arguments, 'ST_LONGFROMGEOHASH', 0)),
        ];
    }

    /**
     * @param list<Evaluable> $arguments Coordinates or a point, followed by maximum length
     */
    public function encode(Frame $frame, array $arguments): ?string
    {
        $point = count($arguments) === 2;
        $first = $point ? FailedGeometry::read($arguments[0], $frame, 'st_geohash') : $arguments[0]->evaluate($frame);
        $longitude = $point ? null : Convert::toDouble($first, $arguments[0]->domain(), $frame->context);
        $latitude = $point ? null : Convert::toDouble($arguments[1]->evaluate($frame), $arguments[1]->domain(), $frame->context);
        $last = $arguments[count($arguments) - 1];
        $length = (new Parts())->index($frame, $last, legacyDisplay: true);
        if ($length === null || ($point ? $first === null : $longitude === null || $latitude === null)) {
            return null;
        }
        if ($point) {
            [$longitude, $latitude] = $this->ordinates((string) Convert::toText($first, $arguments[0]->domain()), $frame);
        }
        if ($longitude < -180 || $longitude > 180) {
            throw DataError::DataOutOfRange->error('longitude', 'st_geohash');
        }
        if ($latitude < -90 || $latitude > 90) {
            throw DataError::DataOutOfRange->error('latitude', 'st_geohash');
        }
        if ($length < 1 || $length > 100) {
            throw DataError::DataOutOfRange->error('max geohash length', 'st_geohash');
        }
        return Geohash::encode($longitude, $latitude, $length);
    }

    /**
     * @return array{float, float} Stored point ordinates in longitude/latitude order
     */
    public function ordinates(string $bytes, Frame $frame): array
    {
        $geometry = Wkb::read($bytes);
        if ($geometry === null || !$geometry->valid()) {
            throw DataError::GisInvalidData->error('st_geohash');
        }
        if ($frame->context->modes->release !== GrammarRelease::MySql5744 && !in_array($geometry->srid, [0, 4326], true)) {
            throw DataError::GeohashUnsupportedSrid->error('st_geohash');
        }
        if ($geometry->type !== 1) {
            throw DataError::IncorrectType->error('point', 'st_geohash');
        }
        return $geometry->points[0];
    }

    /**
     * @param list<Evaluable> $arguments Hash and reference identifier
     */
    public function point(Frame $frame, array $arguments): ?string
    {
        $hash = $arguments[0]->evaluate($frame);
        try {
            return $this->pointValue($frame, $arguments, $hash);
        } catch (\MySqlMemory\Error\SqlError $error) {
            throw $hash === null ? $error : FailedGeometry::condition($error);
        }
    }

    /**
     * @param list<Evaluable> $arguments Hash and reference identifier
     */
    public function pointValue(Frame $frame, array $arguments, int|float|string|null $hash): ?string
    {
        $srid = (new Parts())->index($frame, $arguments[1], true, true);
        $modern = $frame->context->modes->release !== GrammarRelease::MySql5744;
        if ($srid !== null && ($modern || $hash !== null) && ($srid < 0 || $srid > 4294967295)) {
            throw $modern ? DataError::DataOutOfRange->error('SRID', 'st_pointfromgeohash') : DataError::WrongValueForType->error('SRID', (string) $srid, 'st_pointfromgeohash');
        }
        if ($hash === null || $srid === null) {
            return null;
        }
        if ($modern) {
            (new Inputs())->reference($frame, $srid);
        }
        $text = Convert::readable((string) $hash, $arguments[0]->domain());
        return Wkb::write(new Geometry(1, [$this->decode($text, 'st_pointfromgeohash')], [], $srid));
    }

    /**
     * @param list<Evaluable> $arguments The text encoding
     * @param 0|1 $axis Longitude or latitude
     */
    public function coordinate(Frame $frame, array $arguments, string $function, int $axis): ?float
    {
        $hash = $arguments[0]->evaluate($frame);
        return $hash === null ? null : $this->decode(Convert::readable((string) $hash, $arguments[0]->domain()), $function)[$axis];
    }

    /**
     * @return array{float, float} Decoded longitude and latitude
     */
    public function decode(string $text, string $function): array
    {
        return Geohash::decode($text) ?? throw DataError::WrongValueForType->error('geohash', explode("\0", $text, 2)[0], $function);
    }
}
