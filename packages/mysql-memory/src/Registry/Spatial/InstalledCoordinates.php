<?php

declare(strict_types=1);

namespace MySqlMemory\Registry\Spatial;

use RuntimeException;
use SqlSemantics\Contract\GrammarRelease;

/**
 * Composes the built-in coordinate-system definitions for a supported MySQL release.
 *
 * @visibility MySqlMemory
 */
final class InstalledCoordinates
{
    /**
     * Coordinate definitions grouped by projection, reference frame and ellipsoid.
     */
    private const SOURCES = [
        Geographic\AiryCoordinates::class,
        Geographic\BesselCoordinates::class,
        Geographic\ClarkeCoordinates::class,
        Geographic\EverestCoordinates::class,
        Geographic\Grs1980Coordinates::class,
        Geographic\InternationalCoordinates::class,
        Geographic\OtherCoordinates::class,
        Geographic\WgsCoordinates::class,
        LambertConformal\Etrs89Coordinates::class,
        LambertConformal\Nad27Coordinates::class,
        LambertConformal\Nad83Coordinates::class,
        LambertConformal\Nad83HarnCoordinates::class,
        LambertConformal\Nad83Nsr2007Coordinates::class,
        LambertConformal\Nad83Nsr2011Coordinates::class,
        LambertConformal\RegionalCoordinates::class,
        LambertConformal\SingleParallelCoordinates::class,
        LambertConformal\Wgs72BroadcastCoordinates::class,
        LambertConformal\Wgs84Coordinates::class,
        OtherProjectionsCoordinates::class,
        TransverseMercator\Ellipsoids\AiryCoordinates::class,
        TransverseMercator\Ellipsoids\BesselCoordinates::class,
        TransverseMercator\Ellipsoids\ClarkeCoordinates::class,
        TransverseMercator\Ellipsoids\EverestCoordinates::class,
        TransverseMercator\Ellipsoids\Grs1980Coordinates::class,
        TransverseMercator\Ellipsoids\Grs1980AustralasiaCoordinates::class,
        TransverseMercator\Ellipsoids\Grs1980JgdCoordinates::class,
        TransverseMercator\Ellipsoids\Grs1980Nad83Coordinates::class,
        TransverseMercator\Ellipsoids\InternationalCoordinates::class,
        TransverseMercator\Ellipsoids\Krassowsky1940Coordinates::class,
        TransverseMercator\Ellipsoids\OtherCoordinates::class,
        TransverseMercator\Ellipsoids\WgsCoordinates::class,
        TransverseMercator\Frames\Etrs89Coordinates::class,
        TransverseMercator\Frames\Nad27Coordinates::class,
        TransverseMercator\Frames\Nad83Coordinates::class,
        TransverseMercator\Frames\Nad83HarnCoordinates::class,
        TransverseMercator\Frames\Nad83Nsr2007Coordinates::class,
        TransverseMercator\Frames\Nad83Nsr2011FootCoordinates::class,
        TransverseMercator\Frames\Nad83Nsr2011MetreCoordinates::class,
        TransverseMercator\Frames\Nad83Nsr2011UsSurveyFootCoordinates::class,
        TransverseMercator\Frames\Pulkovo1942Coordinates::class,
        TransverseMercator\Frames\Pulkovo1995Coordinates::class,
        TransverseMercator\Frames\Wgs72Coordinates::class,
        TransverseMercator\Frames\Wgs72BroadcastCoordinates::class,
        TransverseMercator\Frames\Wgs84Coordinates::class,
    ];

    /**
     * @return array<int, array{string, ?string, ?int, string, ?string}>
     * @throws RuntimeException When the release is not MySQL
     */
    public static function of(GrammarRelease $release): array
    {
        $legacy = match ($release) {
            GrammarRelease::MySql5651, GrammarRelease::MySql5744, GrammarRelease::MySql8044 => true,
            GrammarRelease::MySql810, GrammarRelease::MySql820, GrammarRelease::MySql830,
            GrammarRelease::MySql847, GrammarRelease::MySql901, GrammarRelease::MySql910 => false,
            GrammarRelease::PostgreSql166, GrammarRelease::PostgreSql172, GrammarRelease::Sqlite3472 => throw new RuntimeException('A MySQL release is required.'),
        };
        $records = [];
        foreach (self::SOURCES as $source) {
            $records += $source::entries();
        }
        if ($legacy) {
            foreach (LegacyCoordinates::changes() as $id => $record) {
                if ($record === null) {
                    unset($records[$id]);
                } else {
                    $records[$id] = $record;
                }
            }
        }
        ksort($records);
        return $records;
    }
}
